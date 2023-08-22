<?php

namespace propelcog\craftorderimport\integrations;

use Cake\Utility\Hash;
use Carbon\Carbon;
use Craft;
use craft\base\ElementInterface;
use propelcog\craftorderimport\elements\CommerceOrder as CommerceOrderElement;
use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\feedme\base\Element;
use craft\feedme\events\FeedProcessEvent;
use craft\feedme\helpers\BaseHelper;
use craft\feedme\helpers\DataHelper;
use craft\feedme\helpers\DateHelper;
use craft\feedme\Plugin;
use craft\feedme\services\Process;
use craft\fields\Matrix;
use craft\fields\Table;
use craft\helpers\Json;
use DateTime;
use Exception;
use yii\base\Event;
use craft\helpers\StringHelper;

use craft\commerce\elements;
use craft\helpers\Db;

use craft\commerce\models\Transaction;
use craft\commerce\records\Transaction as TransactionRecord;

use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\TransactionEvent;
use craft\commerce\helpers\Currency;

use craft\commerce\errors\PaymentSourceException;
use craft\commerce\models\PaymentSource;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;

use craft\commerce\records\Order as OrderRecord;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\commerce\models\LineItem;
use craft\commerce\records\Purchasable as PurchasableRecord;

/**
 *
 * @property-read string $mappingTemplate
 * @property-read mixed $groups
 * @property-write mixed $model
 * @property-read string $groupsTemplate
 * @property-read string $columnTemplate
 */
class CommerceOrder extends Element
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Commerce Orders';

    /**
     * @var string
     */
    public $myPublicVariable  = 0;

    public $myPublicVariableArray  = [];

    /**
     * @var string
     */
    public static string $class = CommerceOrderElement::class;




    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getGroupsTemplate(): string
    {
        return 'order-import/commerce-orders/groups';
    }

    /**
     * @inheritDoc
     */
    public function getColumnTemplate(): string
    {

        return 'order-import/commerce-orders/column';
    }

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'order-import/commerce-orders/map';
    }
    /**
     * @Store Element Value
     */
    public function updatePublicVariable($newValue) {
        $this->myPublicVariable = $newValue;
    }

    public function updatePublicVariableArray( $params = [] ) {
        $eID = $this->elementID();
        $this->myPublicVariableArray[] = $params;
    }
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function getGroups(): array
    {
      //  $this->getOrderFields();
        if (Commerce::getInstance()) {
            return  $this->getOrderFields();
           // return Commerce::getInstance()->getProductTypes()->getEditableProductTypes();
        }

        return [];
    }

    public function getOrderFields(){
       return  $results = $this->_createProductTypeQuery()->all();

    }

    private function _createProductTypeQuery(): Query
    {

        $query = (new Query())
            ->select([
                '*',
            ])
            ->from(['fieldgroups']);

        // in 4.0 `craft\commerce\model\ProductType::$titleFormat` was renamed to `$variantTitleFormat`.

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function getQuery($settings, array $params = []): mixed
    {
        $query = CommerceOrderElement::find()
            ->status(null)
        //   ->typeId($settings['elementGroup'][ProductElement::class])
            ->siteId(Hash::get($settings, 'siteId') ?: Craft::$app->getSites()->getPrimarySite()->id);
        Craft::configure($query, $params);

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function setModel($settings): ElementInterface
    {
        $this->element = new CommerceOrderElement();
      //  $this->element->typeId = $settings['elementGroup'][CommerceOrderElement::class];

        $siteId = Hash::get($settings, 'siteId');

        if ($siteId) {
            $this->element->siteId = $siteId;
        }

        return $this->element;
    }

    /**
     * @Get Latest Element Value
     */
    public function elementCounter(){
        $elements = (new Query())->select(['*'])->from(['elements'])->limit(1)->orderBy('id desc')->one();
        return $elements['id'];
    }
    /**
     * @Get Element Value By ID
     */
    public function elementID(){
        $elements = (new Query())->select(['*'])->from(['elements'])->limit(1)->orderBy('id desc')->one();
        return $elements['id'];
    }
    /**
     * @Get Element Value By ID
     */
    public function FindelementCounter( $eid ){
        $elements = (new Query())->select(['*'])->from(['content'])->where(['elementId' => $eid])->one();
        if( $elements ){
            return true;
        }else{
            return false;
        }
    }
    /**
     * @Find Customer Value By ID
     */
    public function find_commerce_customers( $cid ){
        $elements = (new Query())->select(['*'])->from(['commerce_customers'])->where(['customerId' => $cid])->one();
        if( $elements ){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @Generate Order ID By Mapping Field
     */
    protected function parseId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $refrenceNumber =  substr( MD5( $value ), 0, 7);
        $commerce_orders = (new Query())->select(['id'])->from(['commerce_orders'])->where(['reference'=>$refrenceNumber])->one();
        if( $commerce_orders ){
            $newElementID = $commerce_orders['id'] ;
        }else{
            $newElementID = $this->elementCounter()+1 ;
            $includeAuditColumns = false;
            \Craft::$app->db->createCommand()->insert('elements', ['id'=>$newElementID,'fieldLayoutId' => 1,'type' => 'craft\commerce\elements\Order','enabled' => 1,'uid'=>$this->UUID()],$includeAuditColumns)->execute();
            $this->element->random_order_id($newElementID);
        }
        $this->updatePublicVariable($newElementID);
        return $newElementID;
    }
    /**
     * @Generate Customer ID By Mapping Field
     */
    protected function parseCustomerId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {

        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $this->element = new CommerceOrderElement();
        $values =  $this->element->getCustomCustomerID($value,$feedData['order_customer_full_name']);
        return $values;
    }

    /**
     * @Random Generate UID For Order
     */

    public  function UUID()
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),

			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),

			// 16 bits for "time_hi_and_version", four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res", 8 bits for "clk_seq_low", two most significant bits holds zero and
			// one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,

			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

    protected function parseUid($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
         $value = $this->fetchSimpleValue($feedData, $fieldInfo);
         $values =  $this->UUID();
         return $values;
    }

    /**
     * @Generate Order Number By Mapping Field
     */

    protected function parseNumber($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
         $value = $this->fetchSimpleValue($feedData, $fieldInfo);
         $values =  MD5( $value );
         return $values;
    }

    /**
     * @Generate Reference Number By Mapping Field
     */

    protected function parseReference($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
         $value = $this->fetchSimpleValue($feedData, $fieldInfo);
         $values =  substr( MD5( $value ), 0, 7);
         return $values;
    }

    /**
     * @Save Custom Field Value By Mapping Field
     */

    protected function parseCustomField1($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {


        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField1_aivitrrr' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField1_aivitrrr' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }

     /**
     * @Get Latest Address Field Value
     */

    public function addressID(){
        $addresses = (new Query())->select(['*'])->from(['addresses'])->limit(1)->orderBy('id desc')->one();
        return $addresses['id'];
    }

     /**
     * @Generate Billing Address ID By Mapping Field
     */

    protected function parseBillingAddressId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {


        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
                $address = [
                'countryCode' => $feedData['order_country_code'],
                'addressLine1' => $feedData['order_billing_address'],
                'addressLine2' => $feedData['order_billing_address2'],
                'administrativeArea' => $feedData['order_billing_state'],
                'locality' => $feedData['order_billing_city'],
                'postalCode' => $feedData['order_billing_zip'],
                'firstName' => $feedData['order_billing_first_name'],
                'lastName' => $feedData['order_shipping_last_name'],
                'fullName' => $feedData['order_billing_first_name'].' '.$feedData['order_shipping_last_name']
                ];
        $customerID = $this->element->getCustomCustomerID($feedData['order_customer_email'],$feedData['order_customer_full_name']);
        $billingID = $this->element->getCustomSetBillingAddress($address,$customerID,$elementID);
        return $billingID;
    }

    /**
     * @Generate Shipping Address ID By Mapping Field
     */

    protected function parseShippingAddressId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {

        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
                $address = [
                    'countryCode' => $feedData['Shipping_Country_Code'],
                    'addressLine1' => $feedData['order_shipping_address'],
                    'addressLine2' => $feedData['order_shipping_address2'],
                    'administrativeArea' => $feedData['order_shipping_state'],
                    'locality' => $feedData['order_shipping_city'],
                    'postalCode' => $feedData['order_shipping_zip'],
                    'firstName' => $feedData['order_shipping_first_name'],
                    'lastName' => $feedData['order_shipping_last_name'],
                    'fullName' => $feedData['order_shipping_first_name'].' '.$feedData['order_shipping_last_name']
                ];
        $customerID = $this->element->getCustomCustomerID($feedData['order_customer_email'],$feedData['order_customer_full_name']);
        $billingID = $this->element->getCustomSetShippingAddress($address,$customerID,$elementID);
        return $billingID;
    }

    /**
     * @Generate Order Card Digits By Mapping Field
     */

    protected function parseOrderLastFour($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {

        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }


    /**
     * @Generate Order Transaction ID By Mapping Field
     */

    protected function parseOrderTransactionId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {

        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }


    /**
     * @Generate Order Total Discount By Mapping Field
     */

    protected function parseTotalDiscount($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {

        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }

     /**
     * @Generate Order Vault By Mapping Field
     */

    protected function parseOrderVaultId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }

   /**
     * @Generate Order Payment IP By Mapping Field
     */


    protected function parseLastIp($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }

    /**
     * @Generate Order Payment IP By Mapping Field
     */

    protected function parseShippingMethodAmount($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }

    /**
     * @Generate Order Tax Method By Mapping Field
     */

    protected function parseTaxMethodName($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }

    /**
     * @Generate Order Total Tax Amount By Mapping Field
     */

    protected function parseTotalTax($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        return $value;
    }

    /**
     * @Generate Order Date By Mapping Field
     */

    protected function parseDateOrdered($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
         if( $fieldInfo ){
             $node = $fieldInfo['node'];
               if( $node == 'usedefault' ){
                   return $value;
               }else{
                   $formatting="Y-m-d\\TH:";
                   $dateValue = DateHelper::parseString($value, $formatting);
                    if ($dateValue instanceof Carbon) {
                        $dateValue = $dateValue->toDateTime();
                        return $dateValue;
                    }
               }
         }
    }

    /**
     * @Generate Date Authorized By Mapping Field
     */


    protected function parseDateAuthorized($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
         if( $fieldInfo ){
             $node = $fieldInfo['node'];
               if( $node == 'usedefault' ){
                   return $value;
               }else{
                   $formatting="Y-m-d\\TH:";
                   $dateValue = DateHelper::parseString($value, $formatting);
                    if ($dateValue instanceof Carbon) {
                        $dateValue = $dateValue->toDateTime();
                        return $dateValue;
                    }
               }
         }
    }

    /**
     * @Generate Order Payment Date By Mapping Field
     */

    protected function parseDatePaid($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

         if( $fieldInfo ){
             $node = $fieldInfo['node'];
               if( $node == 'usedefault' ){
                   return $value;
               }else{
                   $formatting="Y-m-d\\TH:";
                   $dateValue = DateHelper::parseString($value, $formatting);
                    if ($dateValue instanceof Carbon) {
                        $dateValue = $dateValue->toDateTime();
                        return $dateValue;
                    }
               }
         }
    }

    /**
     * @Generate Order Gateway ID By Mapping Field
     */

    protected function parseGatewayId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {

         $value = $this->fetchSimpleValue($feedData, $fieldInfo);
         $gaetway = Commerce::getInstance()->getGateways()->getGatewayByHandle($value);
         if( isset($gaetway->id) ){
             return $gaetway->id;
         }
        return $value;
    }
}
