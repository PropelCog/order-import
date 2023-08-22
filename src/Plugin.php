<?php

namespace propelcog\craftorderimport;
use propelcog\craftorderimport\variables\ImportFieldsVariable;
use craft\web\twig\variables\CraftVariable;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use propelcog\craftorderimport\models\Settings;
use craft\feedme\events\RegisterFeedMeElementsEvent;
use craft\feedme\services\Elements;
use yii\base\Event;
use propelcog\craftorderimport\services\Import ;
use propelcog\craftorderimport\integrations\CommerceOrder as CommerceOrderElement;

use craft\feedme\base\Element;
use craft\feedme\events\ElementEvent;

use craft\feedme\events\FeedProcessEvent;
use craft\feedme\services\Process;
use craft\feedme\helpers\DataHelper;
use Cake\Utility\Hash;
/**
 * Order Import plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author PropelCog <staff@propelcog.com>
 * @copyright PropelCog
 * @license MIT
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static $plugin;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;
        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...
        });
         Event::on(Elements::class, Elements::EVENT_REGISTER_FEED_ME_ELEMENTS, function(RegisterFeedMeElementsEvent $e) {


            $e->elements[] = CommerceOrderElement::class;
        });


        $this->setComponents([
            'import' => \propelcog\craftorderimport\services\ImportFields::class,
        ]);
        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $event->sender->set('craftimport', ImportFieldsVariable::class);

            }
        );

        Event::on(Element::class, Element::EVENT_BEFORE_PARSE_ATTRIBUTE, function(ElementEvent $e) {
           $feedData =  $e->feedData;
           $fieldHandle =  $e->fieldHandle;
           $fieldInfo =  $e->fieldInfo;
           /*
           echo "<pre>";
           print_r( $feedData );
           die;
           */

        });

        Event::on(Process::class, Process::EVENT_BEFORE_PROCESS_FEED, function(FeedProcessEvent $event) {
            /*
            echo "<pre>";
            print_r( $event->feedData );
            */
        });

        Event::on(Process::class, Process::EVENT_STEP_BEFORE_PARSE_CONTENT, function(FeedProcessEvent $event) {
            $lineItemFields = array(
                'lineItemstaxCategoryId',
                'lineItemsshippingCategoryId',
                'lineItemsdescription',
                'lineItemsoptions',
                'lineItemsprice',
                'lineItemssaleAmount',
                'lineItemssalePrice',
                'lineItemssku',
                'lineItemsweight',
                'lineItemsheight',
                'lineItemslength',
                'lineItemswidth',
                'lineItemssubtotal',
                'lineItemstotal',
                'lineItemsqty',
                'lineItemsnote',
                'lineItemsprivateNote'
            );
            $lineItems = array();

            $fieldMapping = $event->feed['fieldMapping'];
            if(!isset( $event->feedData['order_total_items'] ))
            {
                return $event;
            }
            $totalItem = $event->feedData['order_total_items'];

            foreach($lineItemFields as $fieldHandle)
            {
                if(isset( $fieldMapping[$fieldHandle] ))
                {
                    $fieldInfo = $fieldMapping[$fieldHandle];
                    $attributeValue = DataHelper::fetchArrayValue($event->feedData, $fieldInfo);
                // $attributeValue = $event->element->parseAttribute($event->feedData, $fieldHandle, $fieldInfo);
                for( $i=0; $i < $totalItem; $i++)
                {
                    $val = isset($attributeValue[$i] )? $attributeValue[$i] : "" ;
                    if(empty($val))
                    {
                        $val = DataHelper::fetchSimpleValue($event->feedData, $fieldInfo);
                    }

                    $lineItems[$i][$fieldHandle] = $val;
                }
                    //   print_r( $attributeValue );
                    // echo $attributeValue;
                    //  echo "\n";
                }
            }

           // echo "<pre>";
          //  print_r( $lineItems );
          //  die;
            $event->element->lineItems = $lineItems;
          //  echo "<pre> sss";
          //  print_r( $event->feedData );
          return $event;

        });

    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('order-import/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/4.x/extend/events.html to get started)
    }
}
