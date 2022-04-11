<?php
/**
 * @class  svitemController
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemController
 */
class svitemController extends svitem
{
/**
 * @brief ./tpl/skin.js/itemdetail.js에서 호출함
 */
	public function procSvitemNaverpay() 
	{
		$nItemSrl = Context::get('item_srl');
		$nOrderedQty = Context::get('ordered_qty');

		$oSvitemModel = &getModel('svitem');
		$nStockAvailable = $oSvitemModel->getItemStock($nItemSrl);
		
		$oSvcartController = &getController('svcart');
		$oRet = $oSvcartController->createCartObj();
		if( !$oRet->toBool() )
			return $oRet;

		$aCartSrl = $oRet->get('cart_srl_arr');
//var_dump( $aCartSrl );
//var_dump( implode(',',$aCartSrl) );
		$this->add( 'cart_srl', $aCartSrl[0]);

		// find connected svorder module info
		$oModuleModel = getModel('module');
		$oSvcartConfig = $oModuleModel->getModuleInfoByMid($sSvcartMid);
		$sMode = Context::get('mode');
		switch( $sMode )
		{
			case 'npay':
				$this->add( 'nStockAvailable', $nStockAvailable );
				break;
		}
	}
/**
 * @brief ./tpl/skin.js/itemdetail.js에서 호출함
 */
	public function procSvitemAddItemsToCartObj() 
	{
		$oSvcartController = &getController('svcart');
		$oRet = $oSvcartController->createCartObj();
		if( !$oRet->toBool() )
			return $oRet;

		$aCartSrl = $oRet->get('cart_srl_arr');
		$this->add( 'cart_srl', implode(',',$aCartSrl)); // 어차피 단품 상황이므로 implode 대신 $aCartSrl[0]으로 변경해야 할
		
		$sSvcartMid = Context::get('svcart_mid');
		if( strlen( $sSvcartMid ) == 0 )
			return new BaseObject(-1, 'svcart mid is not specified');		
		// find connected svorder module info
		$oModuleModel = getModel('module');
		$oSvcartConfig = $oModuleModel->getModuleInfoByMid($sSvcartMid);
		$sMode = Context::get('mode');
		switch( $sMode )
		{
			case 'bn': // in case of buy now
				$nSvorderModuleSrl = (int)$oSvcartConfig->svorder_module_srl;
				if( !$nSvorderModuleSrl )
					return new BaseObject(-1, 'please configure svorder on svcart in advance!');
				
				$oSvorderConfig = $oModuleModel->getModuleInfoByModuleSrl($nSvorderModuleSrl);
				$sSvorderMid = $oSvorderConfig->mid;
				if( strlen( $sSvorderMid ) == 0 )
					return new BaseObject(-1, 'please configure svorder in advance!');

				$this->add( 'svorder_mid', $sSvorderMid );
				break;
			case 'atc':
				$bCheckCart = 'N';
				if( $oSvcartConfig->check_cart == 'Y' )
					$bCheckCart = 'Y';
				$this->add( 'go_to_cart', $bCheckCart );
				break;
		}
	}
/**
 * @brief 
 */
	public function procSvitemAddItemsToFavorites()
	{
		$logged_info = Context::get('logged_info');
		if( !$logged_info )
			return new BaseObject(-1, 'msg_login_required');

		$oSvitemModel = &getModel('svitem');
		$oSvcartController = &getController('svcart');
		$item_srl = $this->_getArrCommaSrls('item_srl');
		foreach( $item_srl as $val )
		{
			$item_info = $oSvitemModel->getItemInfoByItemSrl($val);
			if(!$item_info)
				return new BaseObject(-1, 'Item not found.');

			$args->item_srl = $val;
			$args->member_srl = $logged_info->member_srl;
			$output = $oSvcartController->addItemsToFavorites($args);
			if(!$output->toBool())
				return $output;
		}
	}
/**
 * @brief 콤마로 분리된 문자열을 array타입으로 리턴
 * procSvitemAddItemsToFavorites()에서 호출함, favorite ajax 변수 입력 방식을 콤마에서 배열변수형으로 교체하고
 * 이 함수 폐기
 */
	private function _getArrCommaSrls($key)
	{
		$srls = Context::get($key);
		// explode 함수는 $srls값이 "" 이면 { 0:"" } 을 돌려줘서 요소가 1개가 있는 것으로 처리되므로 문제가 되므로,
		// $srls이 빈문자열일 때 explode로 처리하지 않고 array()로 할당해 준다.
		if ($srls)
			$srls = explode(',',$srls);
		else
			$srls = array();

		return $srls;
	}
/**
 * @brief 
 */
	function updateSalesCount($item_srl, $quantity) 
	{
		if (!$item_srl) return;
		$args->item_srl = $item_srl;
		for ($i = 0; $i < $quantity; $i++)
			executeQuery('svitem.updateSalesCount', $args);
	}
/**
 * @brief svorder.controller.php::updateStock()에서 호출
 */	
	public function setItemStock($nItemSrl, $nStock)
	{
		$args->item_srl = $nItemSrl;
		$args->current_stock = $nStock;
		$output = executeQuery('svitem.updateCurrentStockCount', $args);
		return $output; 
	}
/**
 * @brief 
 */
	function updateExtraVars($item_srl, $name, $value)
	{
		if($item_srl && $name)
		{	
			$args->item_srl = $item_srl;
			$args->name = $name;
			$output = executeQuery('svitem.deleteSvitemExtraVars', $args);
			if(!$output->toBool())
				return $output;
			$args->value = $value;
			$output = executeQuery('svitem.insertSvitemExtraVars', $args);
			if(!$output->toBool())
				return $output;
		}
		return new BaseObject();
	}
}
/* End of file svitem.controller.php */
/* Location: ./modules/svitem/svitem.controller.php */