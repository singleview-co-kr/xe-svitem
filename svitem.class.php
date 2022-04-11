<?php
/**
 * @class  svitem
 * @author singleview(root@singleview.co.kr)
 * @brief  svitem
 */
//require_once(_XE_PATH_.'modules/svitem/ExtraItem.class.php');
class svitem extends ModuleObject
{
	const S_NULL_SYMBOL = '|@|'; // ./svitem.item_admin.php, svitem.item_consumer.php에서 사용
/**
 * @brief 
 **/
	function svitem()
	{
	}
/**
 * @brief 모듈 설치 실행
 **/
	function moduleInstall()
	{
		$oModuleModel = &getModel('module');
		$oModuleController = &getController('module');
		return new BaseObject();
	}
/**
 * @brief 설치가 이상없는지 체크
 **/
	function checkUpdate()
	{
		$oModuleModel = &getModel('module');
		// 2013. 09. 25 when add new menu in sitemap, custom menu add
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'svitem', 'model', 'triggerModuleListInSitemap', 'after'))
			return true;
		return false;
	}
/**
 * @brief 업데이트(업그레이드)
 **/
	function moduleUpdate()
	{
		$oModuleModel = &getModel('module');
		$oModuleController = &getController('module');

		// 2013. 09. 25 when add new menu in sitemap, custom menu add
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'svitem', 'model', 'triggerModuleListInSitemap', 'after'))
			$oModuleController->insertTrigger('menu.getModuleListInSitemap', 'svitem', 'model', 'triggerModuleListInSitemap', 'after');

		return new BaseObject(0, 'success_updated');
	}
/**
 * @brief 
 **/
	function moduleUninstall()
	{
	}
/**
 * @brief 캐시파일 재생성
 **/
	function recompileCache()
	{
	}
}
/* End of file svitem.class.php */
/* Location: ./modules/svitem/svitem.class.php */