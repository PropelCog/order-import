<?php

namespace propelcog\craftorderimport\variables;
use propelcog\craftorderimport\Plugin;

use Craft;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\commerce\helpers\Currency;
use craft\commerce\elements\Product;
use craft\commerce\elements\Order;
use craft\elements\Category;

class ImportFieldsVariable
{
    protected $allowAnonymous = true;

        public function getOrderFields(){

        $query = (new Query())
            ->select([
                'productTypes.descriptionFormat',
                'productTypes.fieldLayoutId',
                'productTypes.handle',
                'productTypes.hasDimensions',
                'productTypes.hasProductTitleField',
                'productTypes.hasVariants',
                'productTypes.hasVariantTitleField',
                'productTypes.id',
                'productTypes.name',
                'productTypes.productTitleFormat',
                'productTypes.skuFormat',
                'productTypes.uid',
                'productTypes.variantFieldLayoutId',
            ])
            ->from(['commerce_producttypes']);

        // in 4.0 `craft\commerce\model\ProductType::$titleFormat` was renamed to `$variantTitleFormat`.
        $commerce = Craft::$app->getPlugins()->getStoredPluginInfo('commerce');
        if (version_compare($commerce['schemaVersion'], '4.0.0', '>=')) {
            $query->addSelect('productTypes.variantTitleFormat');
        } else {
            $query->addSelect('productTypes.titleFormat');
        }

        return $query;
    }
}
