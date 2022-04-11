<?php
/**
 * @class  svitemView
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemView
 */
class svitemView extends svitem
{
	protected $_g_sArchivePath = '';
	public function init()
	{
		// 템플릿 경로 설정
		if( $this->module_info->module != 'svitem' )
			$this->module_info->skin = 'default';
		if( !$this->module_info->skin )
			$this->module_info->skin = 'default';
		if( !$this->module_info->display_caution )
			$this->module_info->display_caution = 'Y';
		$this->setTemplatePath( $this->module_path.'skins/'.$this->module_info->skin );
		Context::set( 'module_info', $this->module_info );

		$this->_g_sArchivePath = _XE_PATH_.'files/svitem/';
	}
/**
 * @brief
 * @return 
 **/
	public function dispSvitemIndex() 
	{
		if( Context::get('item_srl') || Context::get('document_srl') )
            return $this->dispSvitemItemDetail();
        $this->dispSvitemItemList();
	}
/**
 * @brief
 * @return 
 **/
	public function dispSvitemItemList() 
	{
		$oSvitemModel = &getModel('svitem');
		// get module config
		$oConfig = $oSvitemModel->getModuleConfig();
		Context::set('config',$oConfig);
		// get mid level config
		$oMidConfig = $oSvitemModel->getMidLevelConfig( $this->module_info->module_srl);
		$aVarsToCancel = ['module_srl', 'module', 'module_category_srl', 'use_mobile', 'mlayout_srl', 'site_srl', 'mid', 'is_skin_fix', 
							'skin', 'is_mskin_fix', 'mskin', 'browser_title', 'description', 'is_default', 'content', 'mcontent',
							'open_rss', 'header_text', 'footer_text', 'regdate', 'category_display', 'delivery_info', 'layout_srl', 'menu_srl', 'primary_key'];
		foreach( $aVarsToCancel as $nIdx => $sVarTitle )
			unset( $oMidConfig->{$sVarTitle} );
		Context::set('mid_config',$oMidConfig);

		$nModuleSrl = $this->module_info->module_srl;
        $oArgs = new stdClass();
		$oArgs->sPageType = 'catalog';
		$oArgs->nModuleSrl = $this->module_info->module_srl;
		$oRst = $oSvitemModel->getDisplayConf($oArgs);
		Context::set('aAllowedDisplay', $oRst->get('aAllowedDisplay'));
		unset($oArgs);
		unset($oRst);
		///$nCatalogSrl = (int)Context::get('catalog');
		//$oListRst = $oSvitemModel->extractItemListForListPage($nModuleSrl, $nCatalogSrl );
		$oParam = new stdClass();
		$oParam->nModuleSrl = $nModuleSrl;
		$oParam->nCatalogSrl = (int)Context::get('catalog');
		$oParam->sSearchTags = Context::get('tags');
		$oListRst = $oSvitemModel->extractItemListForListPage($oParam);
		if( !$oListRst->toBool() )
			return $oListRst;
		$oCatalog = $oListRst->get('catalog_info');
		Context::set('parent_catalog_list', $oCatalog->parent_catalog_list);
		Context::set('current_catalog_info', $oCatalog->current_catalog_info);
		Context::set('children_catalog_list', $oCatalog->direct_child_catalog_list);
		Context::set('aList', $oListRst->data );
		Context::set('total_count', $oListRst->total_count);
		Context::set('total_page', $oListRst->total_page);
		Context::set('page', $oListRst->page);
		Context::set('page_navigation', $oListRst->page_navigation);
		unset($oListRst);

		$sExtScriptFilename = 'ext_script_list_page_'.$this->module_info->module_srl;
		$bRst = FileHandler::exists( $this->_g_sArchivePath.$sExtScriptFilename.'.html');
		if( $bRst )
		{
			$oTemplate = &TemplateHandler::getInstance();
			$sExtScript = $oTemplate->compile($this->_g_sArchivePath, 'ext_script_list_page_'.$this->module_info->module_srl);
			Context::set('ext_script', $sExtScript );
		}
		$this->setTemplateFile('itemlist');
	}
/**
 * @brief
 **/
	public function dispSvitemItemDetail() 
	{
		$oSvitemModel = &getModel('svitem');
		// get module config
		$oConfig = $oSvitemModel->getModuleConfig();
		Context::set('config',$oConfig);
		// get mid level config
		$oMidConfig = $oSvitemModel->getMidLevelConfig( $this->module_info->module_srl);
		$aVarsToCancel = ['module_srl', 'module', 'module_category_srl', 'use_mobile', 'mlayout_srl', 'site_srl', 'mid', 'is_skin_fix', 
							'skin', 'is_mskin_fix', 'mskin', 'browser_title', 'description', 'is_default', 'content', 'mcontent',
							'open_rss', 'header_text', 'footer_text', 'regdate', 'category_display', 'delivery_info', 'layout_srl', 'menu_srl', 'primary_key'];
		foreach( $aVarsToCancel as $nIdx => $sVarTitle )
			unset( $oMidConfig->{$sVarTitle} );
		Context::set('mid_config',$oMidConfig);

		$nModuleSrl = $this->module_info->module_srl;
        $oArgs = new stdClass();
		$oArgs->sPageType = 'detail';
		$oArgs->nModuleSrl = $this->module_info->module_srl;
		$oRst = $oSvitemModel->getDisplayConf($oArgs);
		Context::set('aAllowedDisplay', $oRst->get('aAllowedDisplay'));
		unset($oArgs);
		unset($oRst);

		// begin - item info
        $oItemParams = new stdClass();
		$oItemParams->nDocumentSrl = Context::get('document_srl');
		$oItemInfo = $oSvitemModel->getItemInfoNewFull($oItemParams);
		unset($oItemParams);
		// end - item info

		// set browser title
		Context::setBrowserTitle(strip_tags($oItemInfo->item_name).' - '.Context::getBrowserTitle());

		if( $oItemInfo->category_node_srl > 0 )
		{
			Context::set('parent_catalog_list', $oItemInfo->oCatalog->parent_catalog_list);
			Context::set('current_catalog_info', $oItemInfo->oCatalog->current_catalog_info);
		}
		Context::set('oItemInfo', $oItemInfo);
		//Context::set('visualArr', $oItemInfo->aGalleryImg);

		if(	Mobile::isMobileCheckByAgent() )
			Context::set('sItemDescription', $oItemInfo->mob_description);
		else
			Context::set('sItemDescription', $oItemInfo->pc_description);

		// for sns share
		Context::set('oSnsInfo', $oItemInfo->oSnsInfo);

		// 아이템별 기본 증정 정책 가져오기
		if( $oItemInfo->conditional_additional_discount_giveaway_item_name != svitemItemConsumer::S_NULL_SYMBOL )
		{
			Context::set('giveaway_item_name', $oItemInfo->conditional_additional_discount_giveaway_item_name); 
			Context::set('giveaway_item_price', $oItemInfo->conditional_additional_discount_giveaway_item_price);
			Context::set('giveaway_item_url', $oItemInfo->conditional_additional_discount_giveaway_item_url);
		}
		// 아이템별 기본 할인 정책 가져오기
		if( $oItemInfo->discounted_price != svitemItemConsumer::S_NULL_SYMBOL )
		{
			Context::set('discounted_price', $oItemInfo->discounted_price);
			Context::set('discount_amount', $oItemInfo->discount_amount);
			Context::set('discount_info', $oItemInfo->discount_info);
		}
		// 아이템별 부가 할인 정책 가져오기
		if( $oItemInfo->additional_conditional_discount_amount > 0 )
		{
			Context::set('fb_app_id', $oItemInfo->fb_app_id);
			Context::set('additional_conditional_discount_amount', $oItemInfo->additional_conditional_discount_amount );
			if( $oItemInfo->additional_conditional_promotion_type['fblike'] == 'Y' )
				Context::set('additional_conditional_discount_info_fblike', $oItemInfo->additional_conditional_discount_info_fblike);
			if( $oItemInfo->additional_conditional_promotion_type['fbshare'] == 'Y' )
				Context::set('additional_conditional_discount_info_fbshare', $oItemInfo->additional_conditional_discount_info_fbshare);
			if( $oItemInfo->additional_conditional_promotion_type['coupon'] == 'Y' )
			{
				if( count($oItemInfo->additional_conditional_discount_info_coupon) > 0 )
					Context::set('additional_conditional_discount_info_coupon', $oItemInfo->additional_conditional_discount_info_coupon);
			}
		}
		// bundling info
		if( $oItemInfo->aFloatingProduct != svitemItemConsumer::S_NULL_SYMBOL )
		{
			Context::set('floating_bundles', $oItemInfo->aFloatingProduct );
			Context::set('bundle_fixed_element', $oItemInfo->aFixedProduct );
			$sEncoded = json_encode( $oItemInfo->aFloatingProduct );
			Context::set('bundle', $sEncoded );
			$sEncodedQtyTable = json_encode( $oItemInfo->aMaxQty );
			Context::set('bundle_qty_table', $sEncodedQtyTable );
		}
		// get related items information
		//$item_info->related_item_srls = $item_info->related_items;
		//if( $item_info->related_item_srls )
		//	$item_info->related_items = $oSvitemModel->getItemListByItemSrls($item_info->related_item_srls);

		// for review and QNA
		if( $oConfig->connected_review_board_srl > 0 )
		{
			$oReviews = $oSvitemModel->getReviews($oItemInfo->item_srl);
			Context::set('reviews_per_page', $oReviews->reviews_per_page);
			Context::set('review_list', $oReviews->data);
			Context::set('review_board_mid', $oReviews->mid);
			Context::set('remaining_review', $oReviews->remaining_reviews);
			Context::set('review_category_srl', $oReviews->category_srl);
		}
		if( $oConfig->connected_qna_board_srl > 0 )
		{
			$oQnas = $oSvitemModel->getQnas($oItemInfo->item_srl);
			Context::set('qnas_per_page', $oQnas->qnas_per_page);
			Context::set('qna_list', $oQnas->data);
			Context::set('qna_board_mid', $oQnas->mid);
			Context::set('remaining_qna', $oQnas->remaining_qnas);
			Context::set('qna_category_srl', $oQnas->category_srl);
		}
		// compile 3rd-party script - begin
		$sExtScriptFilename = 'ext_script_detail_page_'.$this->module_info->module_srl;
		$bRst = FileHandler::exists( $this->_g_sArchivePath.$sExtScriptFilename.'.html');
		if( $bRst )
		{
			$oTemplate = &TemplateHandler::getInstance();
			$sExtScript = $oTemplate->compile($this->_g_sArchivePath, $sExtScriptFilename);
			Context::set('ext_script', $sExtScript );
		}
		// compile 3rd-party script - end
		
		// get naverpay script - begin; Npay 정보는 svcart를 거쳐서 svorder에 질의함; svitem->svcart->svorder 구조 준수
		$oSvcartModel = &getModel('svcart');
		$oRst = $oSvcartModel->getNpayScriptBySvcartMid( $oMidConfig->connected_svcart_module_srl_hidden, 'svitem', $oItemInfo->item_srl, $oItemInfo->current_stock );
		if (!$oRst->toBool())
			return $oRst;
		$aNpayScript = $oRst->get('aNpayScript');
		Context::set('npay_script_handler_global', $aNpayScript['global'] );
		Context::set('npay_script_handler_button', $aNpayScript['btn'] );
		unset($aNpayScript);
		// get naverpay script - end
		$this->setTemplateFile('itemdetail');		
		unset($oItemInfo);
	}
/**
 * brief Naver Pay 상품 정보 연동
 * http://가맹점도메인/가맹점페이지?ITEM_ID=XXX&ITEM_ID=XXX&ITEM_ID=XXX
 * /index.php?module=svitem&act=dispSvitemNpayXml&ITEM_ID=XXX&ITEM_ID=XXX&ITEM_ID=XXX
 * @return none
 **/
	public function dispSvitemNpayXml() 
	{
		$oSvcartModel = &getModel('svcart');
		$oSvcartConfig = $oSvcartModel->getModuleConfig();
		if( !$oSvcartConfig->npay_shop_id || !$oSvcartConfig->npay_btn_key || !$oSvcartConfig->npay_mert_key )  
			return new BaseObject(-1, 'msg_npay_is_not_allowed');

		$oSvitemModel = &getModel('svitem');
		$oNpayXmlList = $oSvitemModel->getNpayEpListXml();
		Context::set('list', $oNpayXmlList );
		echo '<?xml version="1.0" encoding="euc-kr"?>'.PHP_EOL;
		$this->setTemplatePath( $this->module_path.'tpl/');		
		$this->setTemplateFile('naver_pay_xml');
		Context::setResponseMethod('XMLRPC'); 
	}
/**
 * brief Naver EP 연동
 * .htaccess에 아래의 내용 추가해야 함
#naver EP
RewriteRule  ^naver_ep\.tsv index.php?module=svitem&act=dispSvitemNaverEp [L,QSA]
 * @return none
 **/
	public function dispSvitemNaverEp() 
	{
		$oSvitemModel = &getModel('svitem');
		$oNaverEpList = $oSvitemModel->getNaverEpList();
		Context::set('list', $oNaverEpList );
		header("Content-Type:text/plain; charset=UTF-8");
		$this->setTemplatePath( $this->module_path.'tpl/');		
		$this->setTemplateFile('naver_ep');
		Context::setResponseMethod('JSON'); 
	}
/**
 * brief daum EP 연동, daum EP는 euc-kr 기본
 * .htaccess에 아래의 내용 추가해야 함
#daum EP
RewriteRule  ^daum_ep\.txt index.php?module=svitem&act=dispSvitemDaumEp [L,QSA]
 * @return none
 **/
	public function dispSvitemDaumEp() 
	{
		$oSvitemModel = &getModel('svitem');
		$oDaumEpList = $oSvitemModel->getDaumEpList();
		Context::set('list', $oDaumEpList );
		header("Content-Type:text/plain; charset=EUC-KR");
		$this->setTemplatePath( $this->module_path.'tpl/');		
		$this->setTemplateFile('daum_ep');
		Context::setResponseMethod('JSON'); 
	}
/**
 * @brief svitem 스킨에서 호출하는 메쏘드
 * will be deprecated
 */	
	public static function _dispThumbnailUrl( $nThumbFileSrl, $nWidth = 80, $nHeight = 0, $sThumbnailType = 'crop' )
	{
		$sNoimgUrl = Context::getRequestUri().'/modules/svitem/tpl/img/no_img_80x80.jpg';
		if(!$nThumbFileSrl) // 기본 이미지 반환
			return $sNoimgUrl;
		
		if(!$nHeight)
			$nHeight = $nWidth;
		
		// Define thumbnail information
		$sThumbnailPath = 'files/cache/thumbnails/'.getNumberingPath($nThumbFileSrl, 3);
		$sThumbnailFile = $sThumbnailPath.$nWidth.'x'.$nHeight.'.'.$sThumbnailType.'.jpg';
		$sThumbnailUrl = Context::getRequestUri().$sThumbnailFile; //http://127.0.0.1/files/cache/thumbnails/840/80x80.crop.jpg"
		// Return false if thumbnail file exists and its size is 0. Otherwise, return its path
		if(file_exists($sThumbnailFile) && filesize($sThumbnailFile) > 1 ) 
			return $sThumbnailUrl;

		// Target File
		$oFileModel = &getModel('file');
		$sSourceFile = NULL;
		$sFile = $oFileModel->getFile($nThumbFileSrl);
		if($sFile) 
			$sSourceFile = $sFile->uploaded_filename;

		if($sSourceFile)
			$oOutput = FileHandler::createImageFile($sSourceFile, $sThumbnailFile, $nWidth, $nHeight, 'jpg', $sThumbnailType);

		// Return its path if a thumbnail is successfully genetated
		if($oOutput) 
			return $sThumbnailUrl;
		else
			FileHandler::writeFile($sThumbnailFile, '','w'); // Create an empty file not to re-generate the thumbnail
		return $sNoimgUrl;
	}
/**
 * @brief 
 */	
/*	public function _dispExtraTitle($oExtraVars, $sKey)
	{
		if(isset($oExtraVars->{$sKey}->title))
			return $oExtraVars->{$sKey}->title;
		return NULL;
	}*/
/**
 * @brief svitem 스킨에서 호출하는 메쏘드
 */	
/*	public function _dispExtraValue($oExtraVars, $sKey)
	{
		$value = NULL;
		if(isset($oExtraVars->{$sKey}->value))
			$value = $oExtraVars->{$sKey}->value;
		if(is_array($value))
			$value = implode(',',$value);
		return $value;
	}*/
}
/* End of file svitem.view.php */
/* Location: ./modules/svitem/svitem.view.php */