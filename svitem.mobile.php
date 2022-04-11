<?php
/**
 * @class  svitemMobile
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemMobile class
 */
require_once(_XE_PATH_.'modules/svitem/svitem.view.php');
class svitemMobile extends svitemView
{
	function init()
	{
		$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		if(!is_dir($template_path)||!$this->module_info->mskin) 
		{
			$this->module_info->mskin = 'default';
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		}
		$this->setTemplatePath($template_path);

		Context::addJsFile('common/js/jquery.min.js');
		Context::addJsFile('common/js/xe.min.js');

		$this->_g_sArchivePath = _XE_PATH_.'files/svitem/';
	}
}
/* End of file svitem.item.php */
/* Location: ./modules/svitem/svitem.mobile.php */