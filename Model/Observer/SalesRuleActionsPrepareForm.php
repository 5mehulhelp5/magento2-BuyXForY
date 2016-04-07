<?php
namespace Zero1\BuyXForY\Model\Observer;

use Zero1\BuyXForY\Model\Rule;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SalesRuleActionsPrepareForm implements ObserverInterface
{
	/**
	 * @param \Magento\Framework\Event\Observer $observer
	 */
	public function execute(Observer $observer)
	{
		/** @var \Magento\Framework\Data\Form $form */
		$form = $observer->getEvent()->getForm();
		/** @var \Magento\Framework\Data\Form\Element\Select $simpleActionElement */
		$simpleActionElement = $form->getElement('simple_action');

		$values = $simpleActionElement->getValues();
		$values[] = array(
			'value' => Rule::BUY_X_FOR_Y,
			'label' => __('Buy X For Y'),
		);
		$simpleActionElement->setValues($values);
	}
}