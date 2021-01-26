<?php
namespace Zero1\BuyXForY\Model\Rule\Action\Discount;

use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

class BuyXForY extends AbstractDiscount
{
    /**
     * @param \Magento\SalesRule\Model\Validator $validator
     * @param DataFactory $discountDataFactory
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        \Magento\SalesRule\Model\Validator $validator,
        \Magento\SalesRule\Model\Rule\Action\Discount\DataFactory $discountDataFactory,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
    )
    {
        $this->validator = $validator;
        $this->discountFactory = $discountDataFactory;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @param \Magento\SalesRule\Model\Rule $rule
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param float $qty
     * @return \Magento\SalesRule\Model\Rule\Action\Discount\Data
     */
    public function calculate($rule, $item, $qty)
    {
        /** @var \Magento\SalesRule\Model\Rule\Action\Discount\Data $discountData */
        $discountData = $this->discountFactory->create();

        if ($rule->getDiscountStep() <= 0 || $rule->getDiscountQty() < 0) {
            return $discountData;
        }
        //get all items relevant to this rule
        /** @var Quote $quote */
        $quote = $item->getQuote();
        $ruleItems = array_filter($quote->getAllVisibleItems(), function (QuoteItem $item) use ($rule) {
            return in_array($rule->getId(), explode(',', $item->getAppliedRuleIds()));
        });

        //sort lowest to highest
        $validator = $this->validator;
        usort($ruleItems, function (QuoteItem $a, QuoteItem $b) use ($validator) {
            if ($validator->getItemPrice($a) == $validator->getItemPrice($b)) {
                return 0;
            }
            if ($validator->getItemPrice($a) < $validator->getItemPrice($b)) {
                return -1;
            } else {
                return 1;
            }
        });

        //build a flat list of the items
        $itemList = array();
        $skuDiscounts = array();
        /** @var QuoteItem $ruleItem */
        foreach ($ruleItems as $ruleItem) {

            for ($x = 0; $x < $ruleItem->getQty(); $x++) {
                $itemList[] = array(
                    'sku' => $ruleItem->getProduct()->getSku(),
                    'price' => $validator->getItemPrice($ruleItem),
                    'base_price' => $validator->getItemBasePrice($ruleItem),
                );

                $skuDiscounts[$ruleItem->getProduct()->getSku()] = array(
                    'discount' => 0,
                    'base_discount' => 0,
                );
            }
        }

        $targetPrice = $this->priceCurrency->convert($rule->getDiscountAmount(), $item->getQuote()->getStore());
        $targetBasePrice = $rule->getDiscountAmount();

        $discountGroups = array_chunk($itemList, floor($rule->getDiscountStep()));
        $totalDiscountGroups = count($discountGroups);       
        $maxDiscountApplications = min($totalDiscountGroups, ($rule->getDiscountQty() == 0) ? $totalDiscountGroups : $rule->getDiscountQty());

        for ($x = 0; $x < $maxDiscountApplications; $x++) {
            $discountGroup = $discountGroups[$x];

            if(count($discountGroup) < $rule->getDiscountStep()) {
                //file_put_contents('/home/magento/htdocs/var/log/rules.log', "Exiting step =".$x.PHP_EOL, FILE_APPEND);
                continue;
            }
            $totalGroupPrice = 0;
            $totalBaseGroupPrice = 0;

            foreach ($discountGroup as $itemData) {
                $totalGroupPrice += $itemData['price'];
                $totalBaseGroupPrice += $itemData['base_price'];
            }

            $difference = ($totalGroupPrice - $targetPrice);
            $baseDifference = ($totalBaseGroupPrice - $targetBasePrice);

            if ($difference <= 0) {
                continue;
            }

            $discountGroupCount = count($discountGroup);
            $discountPerItem = $difference / $discountGroupCount;
            $baseDiscountPerItem = $baseDifference / $discountGroupCount;

            //use to carry over any discounts > item price
            $discountAmount = 0;
            $discountBaseAmount = 0;

            foreach ($discountGroup as $itemData) {

                $discountAmount += $discountPerItem;
                $discountBaseAmount += $baseDiscountPerItem;

                if ($discountAmount > $itemData['price']) {

                    $discountAmount -= $itemData['price'];
                    $discountBaseAmount -= $itemData['base_price'];

                    $skuDiscounts[$itemData['sku']]['discount'] += $itemData['price'];
                    $skuDiscounts[$itemData['sku']]['base_discount'] += $itemData['base_price'];
                } else {
                    $skuDiscounts[$itemData['sku']]['discount'] += $discountAmount;
                    $skuDiscounts[$itemData['sku']]['base_discount'] += $discountBaseAmount;

                    $discountAmount = 0;
                    $discountBaseAmount = 0;
                }
            }
        }

        if (isset($skuDiscounts[$item->getProduct()->getSku()])) {
            $discountData->setAmount($skuDiscounts[$item->getProduct()->getSku()]['discount']);
            $discountData->setBaseAmount($skuDiscounts[$item->getProduct()->getSku()]['base_discount']);
        }

        return $discountData;
    }
}
