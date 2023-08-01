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

    public function updatePublicVariable($newValue) {
        $this->myPublicVariable = $newValue;
    }    

    public function updatePublicVariableArray( $params = [] ) {
        $eID = $this->elementID();
        $this->myPublicVariableArray[] = $params;
        /*
        echo "<pre>";
        print_r( $this->myPublicVariableArray );
        */

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
     * @inheritDoc
     */
    /*
    public function save($element, $settings): bool
    {
        $this->beforeSave($element, $settings);

        if (!Craft::$app->getElements()->saveElement($this->element, true, true, Hash::get($this->feed, 'updateSearchIndexes'))) {
            $errors = [$this->element->getErrors()];

            if ($this->element->getErrors()) {
                foreach ($this->element->getVariants() as $variant) {
                    if ($variant->getErrors()) {
                        $errors[] = $variant->getErrors();
                    }
                }

                throw new Exception(Json::encode($errors));
            }

            return false;
        }

        return true;
    }
    */
    public function elementCounter(){
        $elements = (new Query())->select(['*'])->from(['elements'])->limit(1)->orderBy('id desc')->one();
        return $elements['id'];
    }
    public function elementID(){
        $elements = (new Query())->select(['*'])->from(['elements'])->limit(1)->orderBy('id desc')->one();
        return $elements['id'];
    }
    public function FindelementCounter( $eid ){
        $elements = (new Query())->select(['*'])->from(['content'])->where(['elementId' => $eid])->one();
        if( $elements ){
            return true;
        }else{
            return false;
        }
    }
    public function find_commerce_customers( $cid ){
        $elements = (new Query())->select(['*'])->from(['commerce_customers'])->where(['customerId' => $cid])->one();
        if( $elements ){
            return true;
        }else{
            return false;
        }
    }
  
    protected function parseId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        
        $newElementID = $this->elementCounter()+1 ;
        $includeAuditColumns = false;
        \Craft::$app->db->createCommand()->insert('elements', ['id'=>$newElementID,'fieldLayoutId' => 1,'type' => 'craft\commerce\elements\Order','enabled' => 1,'uid'=>$this->UUID()],$includeAuditColumns)->execute();
        //\Craft::$app->db->createCommand()->insert('commerce_orders', ['id'=>$newElementID,'orderLanguage'=>'en'],$includeAuditColumns)->execute();
        $this->element->random_order_id($newElementID);
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $this->updatePublicVariable($newElementID);


       
       

        return $newElementID;
    }
    
    protected function parseCustomerId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        $this->element = new CommerceOrderElement();
        $values =  $this->element->getCustomCustomerID($value,$feedData['Customer_Full_Name']);
        return $values;
    }
    /*
    public function UUID()
    {
        return StringHelper::UUID();
    }

     public static function UUID()
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
    protected function parseNumber($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
         $value = $this->elementID(); 
         $values =  MD5( $value );
         return $values;
    }
    protected function parseReference($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
         $value = $this->elementID(); 
         $values =  substr( MD5( $value ), 0, 7);
         return $values;
    }
    
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
    /*
    protected function parseCustomField2($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField2_amfyxsjv' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField2_amfyxsjv' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField3($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField3_wnmbdqkw' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField3_wnmbdqkw' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField4($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField4_hoyetofw' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField4_hoyetofw' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField5($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField5_tzahvdna' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField5_tzahvdna' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField6($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField6_hwosloai' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField6_hwosloai' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField7($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField7_vqioyvms' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField7_vqioyvms' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField8($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField8_cztviudc' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField8_cztviudc' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField9($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField9_xcsjeqii' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField9_xcsjeqii' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField10($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField10_hkmgkgri' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField10_hkmgkgri' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField11($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField11_wzuddsml' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField11_wzuddsml' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    protected function parseCustomField12($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    { 
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $FindelementCounter = $this->FindelementCounter( $elementID );
        if( $FindelementCounter ){
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->update('content', [
                'field_customField12_zlcwoznv' => $value
            ],'elementId ='.$elementID)->execute();
        }else{
            $includeAuditColumns = false;
            $results = \Craft::$app->db->createCommand()->insert('content', [
                'elementId' => $elementID,
                'siteId' => 1,
                'field_customField12_zlcwoznv' => $value,
                'uid'=>$this->UUID()
            ],$includeAuditColumns)->execute();
        }
        return $value;
    }
    */
    public function addressID(){
        $addresses = (new Query())->select(['*'])->from(['addresses'])->limit(1)->orderBy('id desc')->one();
        return $addresses['id'];
    }
    protected function parseBillingAddressId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {


        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
                $address = [
                'countryCode' => $feedData['Billing_Country_Code'],
                'addressLine1' => $feedData['Billing_Address'],
                'addressLine2' => $feedData['Billing_Address_2'],
                'administrativeArea' => $feedData['Billing_State'],
                'locality' => $feedData['Billing_City'],
                'postalCode' => $feedData['Billing_Zip'],
                'firstName' => $feedData['Billing_First_Name'],
                'lastName' => $feedData['Billing_Last_Name'],
                'fullName' => $feedData['Billing_First_Name'].' '.$feedData['Billing_Last_Name']
                ];
        $customerID = $this->element->getCustomCustomerID($feedData['Customer_Email'],$feedData['Customer_Full_Name']);
        $billingID = $this->element->getCustomSetBillingAddress($address,$customerID,$elementID);
        return $billingID;
    }
    protected function parseShippingAddressId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        
        $elementID = $this->myPublicVariable;
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
                $address = [
                    'countryCode' => $feedData['Shipping_Country_Code'],
                    'addressLine1' => $feedData['Shipping_Address'],
                    'addressLine2' => $feedData['Shipping_Address_2'],
                    'administrativeArea' => $feedData['Shipping_State'],
                    'locality' => $feedData['Shipping_City'],
                    'postalCode' => $feedData['Shipping_Zip'],
                    'firstName' => $feedData['Shipping_First_Name'],
                    'lastName' => $feedData['Shipping_Last_Name'],
                    'fullName' => $feedData['Shipping_First_Name'].' '.$feedData['Shipping_Last_Name']
                ];
        $customerID = $this->element->getCustomCustomerID($feedData['Customer_Email'],$feedData['Customer_Full_Name']);
        $billingID = $this->element->getCustomSetShippingAddress($address,$customerID,$elementID);
        return $billingID;
    }
    protected function parseOrderLastFour($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
    protected function parseOrderTransactionId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
    protected function parseTotalDiscount($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
    protected function parseOrderVaultId($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
   
    protected function parseLastIp($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
    protected function parseShippingMethodAmount($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
    protected function parseTaxMethodName($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
    protected function parseTotalTax($feedData, $fieldInfo): DateTime|bool|array|Carbon|string|null
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo); 
        return $value;
    }
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
