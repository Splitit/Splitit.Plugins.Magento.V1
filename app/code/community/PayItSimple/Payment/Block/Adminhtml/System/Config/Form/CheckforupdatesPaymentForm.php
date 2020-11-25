<?php

class PayItSimple_Payment_Block_Adminhtml_System_Config_Form_CheckforupdatesPaymentForm extends Mage_Adminhtml_Block_System_Config_Form_Field
{
        /*
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payitsimple/system/config/checkforupdatespaymentform.phtml');
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function checkforupdates()
    {
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) // store level
        {
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        }
        elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) // website level
        {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        }
        else // default level
        {
            $store_id = 0;
        }
        return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_payitsimple/checkforupdatesPaymentForm', array('store_id' => $store_id));
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
            'id'        => 'payitsimple_checkforupdates_payment_form',
            'label'     => $this->helper('adminhtml')->__('Check For Updates'),
            'onclick'   => 'javascript:checkForUpdatesPaymentForm(); return false;'
        ));

        return $button->toHtml();
    }

}