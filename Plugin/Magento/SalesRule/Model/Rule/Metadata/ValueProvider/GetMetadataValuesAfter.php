<?php
namespace Zero1\BuyXForY\Plugin\Magento\SalesRule\Model\Rule\Metadata\ValueProvider;

use Zero1\BuyXForY\Model\Rule;

class GetMetadataValuesAfter
{
    /**
     * @param $valueProvider \Magento\SalesRule\Model\Rule\Metadata\ValueProvider\Interceptor
     * @param $metaDataValues
     */
    public function afterGetMetadataValues($valueProvider, $metaDataValues)
    {
        if(isset(
            $metaDataValues['actions'],
            $metaDataValues['actions']['children'],
            $metaDataValues['actions']['children']['simple_action'],
            $metaDataValues['actions']['children']['simple_action']['arguments'],
            $metaDataValues['actions']['children']['simple_action']['arguments']['data'],
            $metaDataValues['actions']['children']['simple_action']['arguments']['data']['config'],
            $metaDataValues['actions']['children']['simple_action']['arguments']['data']['config']['options']
        )){
            $metaDataValues['actions']['children']['simple_action']['arguments']['data']['config']['options'][] = [
                'label' => __('Buy X For Y'),
                'value' => Rule::BUY_X_FOR_Y,
            ];
        }

        return $metaDataValues;
    }
}