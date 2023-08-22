<?php
/**
 * Customise plugin for Craft CMS 3.x
 *
 * Custom import channel
 *
 * @link      https://www.mondolux.com.au/
 * @copyright Copyright (c) 2022 mondolux
 */

namespace namespace propelcog\craftorderimport\services;

use hsstudio\dataimport\Dataimport;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Category as CategoryElement;
use craft\services\Fs as FsService;
use craft\records\VolumeFolder as VolumeFolderRecord;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\errors\AssetException;
use craft\helpers\Json;
use craft\i18n\Locale;
use craft\models\AssetIndexingSession;
/**
 * Import Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    mondolux
 * @package   Customise
 * @since     1.0.1
 */
class ImportFields extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     Customise::$plugin->import->exampleService()
     *
     * @return mixed
     */

     public function getOrderFeedById(int $id): ?NavModel
     {
         return $this->_navs()->firstWhere('id', $id);
     }

    public function exampleService()
    {
        $result = 'something';
        // Check our Plugin's settings for `someAttribute`
        if (Customise::$plugin->getSettings()->someAttribute) {
        }

        return $result;
    }
    public function exampleService2()
    {
        $result = 'something';
        return $result;
    }
}
