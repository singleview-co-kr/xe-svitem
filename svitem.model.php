<?php
/**
 * @class  svitemModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemModel
 */
//require_once(_XE_PATH_.'modules/svitem/svitem.item.php');
class svitemModel extends svitem
{
	private $_g_aCatalogDefaultDisplay = array('checkbox', 'image', 'title', 'quantity', 'amount', 'cart_buttons', 'sales_count' );
	private $_g_aDetailDefaultDisplay = array( );
/**
 * @brief
 * @return 
 **/
	public function init() 
	{
		if (!$this->module_info->thumbnail_width)
			$this->module_info->thumbnail_width = 150;
		if (!$this->module_info->thumbnail_height)
			$this->module_info->thumbnail_height = 150;
	}
/**
 * @brief
 * @return 
 **/
	public function getModuleConfig()
	{
		$oModuleModel = &getModel('module');
		$oConfig = $oModuleModel->getModuleConfig('svitem');
		if(is_null($oConfig))
			$oConfig = new stdClass();
		if(!$oConfig->cart_thumbnail_width) $oConfig->cart_thumbnail_width = 100;
		if(!$oConfig->cart_thumbnail_height) $oConfig->cart_thumbnail_height = 100;
		if(!$oConfig->favorite_thumbnail_width) $oConfig->favorite_thumbnail_width = 100;
		if(!$oConfig->favorite_thumbnail_height) $oConfig->favorite_thumbnail_height = 100;
		if(!$oConfig->order_thumbnail_width) $oConfig->order_thumbnail_width = 100;
		if(!$oConfig->order_thumbnail_height) $oConfig->order_thumbnail_height = 100;
		if(!$oConfig->address_input) $oConfig->address_input = 'krzip';
		
		$oConfig->currency = 'KRW';
		$oConfig->as_sign = 'Y';
		$oConfig->decimals = 0;
		return $oConfig;
	}
/**
 * @brief get mid level config
 * @return 
 **/
	public function getMidLevelConfig($nModuleSrl)
	{
		if( !$nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_module_srl');
		$oModuleModel = &getModel('module');
		return $oModuleModel->getModuleInfoByModuleSrl($nModuleSrl);
	}
/**
 * @brief get display config by module_srl and page_type
 * @return 
 **/
	public function getDisplayConf($oParam)
	{
		if( $oParam->sPageType != 'catalog' && $oParam->sPageType != 'detail' )
			return new BaseObject(-1, 'msg_invalid_page_type');

		if( !$oParam->nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_module_srl');

		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
		$aAllowedDisplay = [];
		$oRst = $oExtraVarsController->getExtraVarsConfiguration($oParam);
		if(!$oRst->toBool()) 
			return $oRst;
		$aTemp = $oRst->get('aDisplayingVars');

		unset($oRst);
		foreach( $aTemp as $sVarName => $oDisplay )
			$aAllowedDisplay[$sVarName] = 'Y';
		
		$oRst = new BaseObject();
		$oRst->add('aAllowedDisplay',$aAllowedDisplay);
		return $oRst;
	}
/**
 * @brief extract item list 
 **/
	public function extractItemListForListPage($oParam)
	{
		$nModuleSrl = (int)$oParam->nModuleSrl;
		$nCatalogSrl = (int)$oParam->nCatalogSrl;
		$sSearchTags = $oParam->sSearchTags;
		$list_count = Context::get('disp_numb');
		if(!$list_count && $this->module_info->list_count)
			$list_count = $this->module_info->list_count;

		$sort_index = Context::get('sort_index');
		if(!$sort_index)
			$sort_index = "list_order";

		$order_type = Context::get('order_type');
		if(!$order_type)
			$order_type = 'asc';

		// item list
        $args = new stdClass();
		$args->module_srl = $nModuleSrl;
		$args->display = 'Y';
		$args->page = Context::get('page');
		$args->list_count = $list_count;
		$args->sort_index = $sort_index;
		$args->order_type = $order_type;
		if(is_null( $args->page))
			$args->page = 1;

		$oCatalog = $this->getCatalog($nModuleSrl, $nCatalogSrl);
		if($sSearchTags) // 검색 태그 입력되면, 태그 검색 모드
		{
			$aSearchTag = explode(',', $sSearchTags);
			/*$sCatalogItemListCacheFile = FileHandler::readFile('./files/cache/svitem/catalog_itemlist.'.$nModuleSrl.'.'.$nCatalogSrl.'.'.$args->page.'.cache.php');
			if($sCatalogItemListCacheFile)
				$oRst = unserialize( $sCatalogItemListCacheFile );
			else*/
			$oRst = $this->_compileTagItemListCache($nModuleSrl, $aSearchTag, $args);
		}
		else // 카탈로그 모드가 기본
		{
			if($nCatalogSrl) // 카테고리 입력되면, category_node_srl 검색
				$args->category_node_srls = $oCatalog->displaying_catalog_srls;

			$sCatalogItemListCacheFile = FileHandler::readFile('./files/cache/svitem/catalog_itemlist.'.$nModuleSrl.'.'.$nCatalogSrl.'.'.$args->page.'.cache.php');
			if($sCatalogItemListCacheFile)
				$oRst = unserialize($sCatalogItemListCacheFile);
			else
				$oRst = $this->_compileCatalogItemListCache($nModuleSrl, $nCatalogSrl, $args);
		}
		
		require_once(_XE_PATH_.'modules/svitem/svitem.item_consumer.php');
		$aItemOnCatalog = [];
		$oSvpromotionModel = &getModel('svpromotion');
		foreach($oRst->data as $nIdx => $oVal)
		{
			$oItemConsumer = new svitemItemConsumer();
            $oParams = new stdClass();
			$oParams->mode = 'import';
			$oParams->oRawData = $oVal;
			$oTmpRst = $oItemConsumer->loadHeader($oParams);
			if(!$oTmpRst->toBool())
				return new BaseObject(-1,'msg_invalid_item_request');
			unset($oTmpRst);
			
			$oApplyRst = $oItemConsumer->applyPromotionForCatalog($oSvpromotionModel);
			if(!$oApplyRst->toBool())
				return $oApplyRst;
			unset($oApplyRst);

			$aItemOnCatalog[] = $oItemConsumer;
		}
		unset($oRst->data);
		$oRst->data = $aItemOnCatalog;
		$oRst->add('catalog_info', $oCatalog);
		return $oRst;
	}
/**
 * @brief extract information about catalog belonged item from DB
 **/
	private function _compileTagItemListCache($nModuleSrl, $aSearchTag, $oArgs)
	{
		if( !$oArgs )
			return new BaseObject(-1, 'msg_invalid_request');
		$aTaggedItem = [];
		foreach($aSearchTag as $nIdx=>$sTag)
		{
			if(strlen($sTag))
			{
				$oArgs->sv_tags = $sTag;
				//iconv( "UTF-8", "EUC-KR", $sTag );
				$oRst = executeQueryArray('svitem.getTaggedItemList', $oArgs);
				if (!$oRst->toBool()) 
					return $oRst;
				foreach($oRst->data as $nIdx=>$oRec)
					$aTaggedItem[(int)$oRec->item_srl] = $oRec;
			}
		}
		$aRst = [];
		if(count($aTaggedItem))
		{
			foreach($aTaggedItem as $nItemSrl=>$oRec)
				$aRst[] = $oRec;
		}
		unset($oRst->data);
		$oRst->data = $aRst;
		//$sSerializedRst = serialize( $oRst );
		//FileHandler::writeFile('./files/cache/svitem/catalog_itemlist.'.$nModuleSrl.'.'.$nCatalogSrl.'.'.$oArgs->page.'.cache.php', $sSerializedRst);
		return $oRst;
	}
/**
 * @brief 상세페이지 buy now 와 add cart 버튼 클릭 시 대응 동작 확인 ajax
 * item class로 구현해야 함
 */
	public function getSvitemDetailPageAction()
	{
		$nItemSrl = (int)Context::get('item_srl');
		if( $nItemSrl == 0 )
			return new BaseObject(-1, 'msg_invalid_request');

		$item_info = $this->getItemInfoByItemSrl($nItemSrl);
		$oExtraVars = unserialize( $item_info->extmall_log_conf );
		$this->add('sExtmallLoggerToggle', $oExtraVars->toggle );
		$args = new stdClass();
		$args->item_srl = $nItemSrl;
		$args->is_active = 'Y';
		$output = executeQueryArray('svitem.getExtmallListByItemSrl', $args);
		
		if( count( $output->data ) > 0 )
		{
			foreach( $output->data as $key=>$val)
				$sMarketOutlinkHtml .= '<A HREF="'.$val->url.'" onclick=\'checkNonEcConversionGaectk("/go_extmall_to_buy.html", "go_extmall_to_buy");\'>'.$val->title.' 구매하러 가기 클릭!</A><BR><BR>';
		}
		else
			$sMarketOutlinkHtml = '외부몰 가격 안내 준비 중입니다!';

		$sLayerHtml = "<div class='svitem_layer'>
			<div class='bg'></div>
			<div id='layer_ext_mall' class='pop-layer'>
				<div class='pop-container'>
					<div class='pop-conts'>
						<div style='width: 100%; margin: 10px 0 20px; border-bottom: 1px solid #DDD; text-align: right;'><a href='#' class='cbtn'>Close</a></div>
						<CENTER>".$sMarketOutlinkHtml."</CENTER>
					</div>
				</div>
			</div>
		</div>";
		$this->add('sLayerHtml', $sLayerHtml );
	}
/**
 * @brief 아이템별 부가 할인 정책 가져오기
 * item class로 구현해야 함
 */
	public function getSvitemConditionalPromoInfo()
	{
		$nItemSrl = (int)Context::get('item_srl');
		if( $nItemSrl == 0 )
			return new BaseObject(-1, 'msg_invalid_request');

		$sPromotionType = Context::get('promotion_type');
		if( strlen( $sPromotionType ) == 0 )
			return new BaseObject(-1, 'msg_invalid_request');

		$oItemInfo = $this->getItemInfoByItemSrl( $nItemSrl );
		$oSvpromotionModel = &getModel('svpromotion');
		$oConditionalPromotion = $oSvpromotionModel->getItemConditionalPromotionDetail( $oItemInfo, $sPromotionType );

		if( $oConditionalPromotion->conditional_additional_discount_amount > 0 )
			$this->add('nConditionalPromoAmnt', $oConditionalPromotion->conditional_additional_discount_amount );
		else
			$this->add('nConditionalPromoAmnt', 0 );
	}
/**
 * @brief $oInArg->nItemSrl or $oInArg->nDocumentSrl or $oInArg->sItemCode or $oInArg->sItemBarcode
 */
	public function getItemInfoNewFull($oInArg)
	{
        $oParams = new stdClass();
		if($oInArg->nDocumentSrl) // svitem.view.php::dispSvitemItemDetail()에서 호출
			$oParams->nDocumentSrl = $oInArg->nDocumentSrl;
		elseif($oInArg->nItemSrl)
			$oParams->nItemSrl = $oInArg->nItemSrl;
		elseif($oInArg->sItemCode)
			$oParams->sItemCode = $oInArg->sItemCode;
		elseif($oInArg->sItemBarcode)
			$oParams->sItemBarcode = $oInArg->sItemBarcode;
		else
			return new BaseObject(-1,'msg_invalid_item_request');

		$oSvitemModuleConfig = $this->getModuleConfig();
		require_once(_XE_PATH_.'modules/svitem/svitem.item_consumer.php');
		$oItemConsumer = new svitemItemConsumer();
		$oParams->mode = 'retrieve';
		$oParams->oSvitemModuleConfig = $oSvitemModuleConfig;
		$oTmpRst = $oItemConsumer->loadHeader($oParams);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_item_request');
		unset($oTmpRst);
		if($oInArg->nDocumentSrl) // svitem.view.php::dispSvitemItemDetail()에서 호출
		{
			$oApplyRst = $oItemConsumer->applyPromotionForDetail();
			if(!$oApplyRst->toBool())
				return $oApplyRst;
			unset($oApplyRst);
		}
		$oDetailRst = $oItemConsumer->loadDetail();
		if(!$oDetailRst->toBool())
			return $oDetailRst;
		unset($oDetailRst);
		return $oItemConsumer;
	}
/**
 * @brief will be deprecated 
 * item class로 구현해야 함
 * @return 
 **/
	public function getProductionCostByTag($sFinanceTag)
	{
		$oArgs->fianance_tag = $sFinanceTag;
		$oRst = executeQueryArray('svitem.getLatestProductionCostByTag', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		unset($oArgs);
		$oFinanceInfo = array_shift($oRst->data);
		$oRst->add('fProductionCost', (float)$oFinanceInfo->production_cost);
		$oRst->add('fNormalPrice', (float)$oFinanceInfo->normal_price);
		unset($oRst->data);
		return $oRst;
	}
/**
 * @brief will be deprecated 
 * item class로 구현해야 함
 * @return 
 **/
	public function getItemByCode($sItemCode)
	{
        $oArgs = new stdClass();
		$oArgs->item_code = $sItemCode;
		$oItem = $this->_getItemInfo($oArgs);
		return $oItem;
	}
/**
 * @brief will be deprecated 
 * item class로 구현해야 함
 * @return 
 **/
	public function getItemInfoByItemSrl($nItemItemSrl) 
	{
		$oArgs = new stdClass();
		$oArgs->item_srl = $nItemItemSrl;
		$oItem = $this->_getItemInfo($oArgs);
		return $oItem;
	}
/**
 * @brief will be deprecated  
 * getItemInfoByItemSrl()의 종속 메소드로 변경해야 하나?
 * item class로 구현해야 함
 * @return 
 **/	
	public function getItemStock($nItemSrl)
	{
		$config = $this->getModuleConfig();
        $oArgs = new stdClass();
		$oArgs->item_srl = $nItemSrl;
		$oItem = $this->_getItemInfo($oArgs);
		if(is_null($oItem))
			return 0;

		$nDefaultSafeStock = (int)$config->default_safe_stock;
		$nSafeStockByItem = (int)$oItem->safe_stock;
		$nSafeStock = $nDefaultSafeStock;

		if($nSafeStockByItem > 0)
			$nSafeStock = $nSafeStockByItem;

		if($config->external_server_type == 'ecaso')
		{
			require_once(_XE_PATH_.'modules/svitem/ext_class/ecaso.class.php');
			$oExtServer = new ecaso($oItem->item_code);
			$nCurrentStock = $oExtServer->getStock();
		}
		else
			$nCurrentStock = (int)$oItem->current_stock;

		if($nSafeStock >= $nCurrentStock)
			return 0;
		else 
			return $nCurrentStock;
	}
/**
 * @brief cameron widget과 호환을 위한 wrapper 폐기 예정
 **/
	public function getFrontDisplayItems($nModuleSrl, $nCatalogSrl, $nMaxSize) 
	{
		return $this->_getWidgetShowWindowDisplayItems($nModuleSrl, $nCatalogSrl, $nMaxSize);
	}
/**
 * @brief show winodw item list for widget call
 **/
	public function getWidgetShowWindowDisplayItems($nModuleSrl, $nCatalogSrl, $nMaxSize) 
	{
		return $this->_getWidgetShowWindowDisplayItems($nModuleSrl, $nCatalogSrl, $nMaxSize);
	}
/**
 * @brief cameron widget과 호환을 위한 wrapper 폐기 예정
 **/
	public function getDisplayItems($nModuleSrl, $nCatalogSrl, $nMaxSize) 
	{
		return $this->_getWidgetDefaultCatalogItems($nModuleSrl, $nCatalogSrl, $nMaxSize);
	}
/**
 * @brief show winodw item list for widget call
 **/
	public function getWidgetDefaultCatalogItems($nModuleSrl, $nCatalogSrl, $nMaxSize) 
	{
		return $this->_getWidgetDefaultCatalogItems($nModuleSrl, $nCatalogSrl, $nMaxSize);
	}
/**
 * @brief Get a list of review
 * @return
 **/
	public function getQnas( &$item_info )
	{
		$logged_info = Context::get('logged_info');
		$oConfig = $this->getModuleConfig();
		if( !$oConfig->connected_qna_board_srl )
			return null;
		
		$nCurrentQna = $this->getQnaCnt($item_info->item_srl);
		$oModuleModel = &getModel('module');
		$oQnaBoardConfig = $oModuleModel->getModuleInfoByModuleSrl($oConfig->connected_qna_board_srl);
		$oDocumentModel = getModel('document');
		$category_content = $oDocumentModel->getCategoryPhpFile($oConfig->connected_qna_board_srl);
		require($category_content );
		$nLoadQnaCount = 0;
		$aQnas = new stdClass();
		foreach( $oConfig->qna_for_item[$item_info->item_srl] as $key=>$val)
		{
			if( $val == 'match' )
			{
				$bExceptNotice = true;
				$bLoadExtraVars = false;
				$args->category_srl = $menu->list[$key]['category_srl'];
				$args->sort_index = $oConfig->order_target?$oConfig->order_target:'list_order';
				$args->order_type = $oConfig->order_type?$oConfig->order_type:'asc';
				$args->list_count = $oConfig->max_qnas_cnt ? $oConfig->max_qnas_cnt+1 : 2+1;
				$output = $oDocumentModel->getDocumentList($args, $bExceptNotice, $bLoadExtraVars);
				foreach($output->data as $revkey=>$revval)
				{
					if( $revval->variables['status'] == 'TEMP' ) // 임시저장 후기를 숨김
						continue;

					$aQnas->data[$revval->document_srl]->title = $revval->variables['title'];
					$aQnas->data[$revval->document_srl]->member_srl = $revval->variables['member_srl'];
					if($aQnas->data[$revval->document_srl]->member_srl!=0 && $logged_info->member_srl == $aQnas->data[$revval->document_srl]->member_srl)
						$aQnas->data[$revval->document_srl]->isGranted = true;
					else
						$aQnas->data[$revval->document_srl]->isGranted = false;

					$aQnas->data[$revval->document_srl]->nick_name = $revval->variables['nick_name'];
					$aQnas->data[$revval->document_srl]->regdate = $revval->variables['regdate'];
					$aQnas->data[$revval->document_srl]->readed_count = $revval->variables['readed_count'];
					$aQnas->data[$revval->document_srl]->voted_count = $revval->variables['voted_count'];
					$aQnas->data[$revval->document_srl]->blamed_count = $revval->variables['blamed_count'];
					$aQnas->data[$revval->document_srl]->comment_count = $revval->variables['comment_count'];
					$nLoadQnaCount++;
				}
			}
		}
		$aQnas->mid = $oQnaBoardConfig->mid;
		if(count($aQnas->data))
		{
			$aQnas->qnas_per_page = $oConfig->qnas_per_page ? $oConfig->qnas_per_page : 2;
			$aQnas->remaining_qnas = $nCurrentQna - $nLoadQnaCount;
			$aQnas->category_srl = $args->category_srl;
		}
		return $aQnas;
	}
/**
 * @brief
 */
	public function getQnaCnt($nItemSrl)
	{
		$oConfig = $this->getModuleConfig();
		if( $oConfig->connected_qna_board_srl > 0 )
		{
			$oDocumentModel = getModel('document');
			$category_content = $oDocumentModel->getCategoryPhpFile($oConfig->connected_qna_board_srl);

			require($category_content );
			$nQnaCnt = 0;
			foreach( $oConfig->qna_for_item[$nItemSrl] as $key=>$val)
			{
				if( $val == 'match' )
					$nQnaCnt += (int)$menu->list[$key]['document_count'];
			}
		}
		else
			$nQnaCnt = 0;
		return $nQnaCnt;
	}
/**
 * @brief 
 */
	public function getReviewCnt($nItemSrl)
	{
		$oConfig = $this->getModuleConfig();
		if( $oConfig->connected_review_board_srl > 0 )
		{
			$oDocumentModel = getModel('document');
			$category_content = $oDocumentModel->getCategoryPhpFile($oConfig->connected_review_board_srl);

			require($category_content );
			$nReviewCnt = 0;
			foreach( $oConfig->review_for_item[$nItemSrl] as $key=>$val)
			{
				if( $val == 'match' )
					$nReviewCnt += (int)$menu->list[$key]['document_count'];
			}
		}
		else
			$nReviewCnt = 0;
		return $nReviewCnt;
	}
/**
 * @brief Get a list of review
 * @return
 **/
	public function getReviews( &$item_info )
	{
		$logged_info = Context::get('logged_info');
		$oConfig = $this->getModuleConfig();
		if( !$oConfig->connected_review_board_srl )
			return null;

		$nCurrentReview = $this->getReviewCnt($item_info->item_srl);
		$oModuleModel = &getModel('module');
		$oReviewBoardConfig = $oModuleModel->getModuleInfoByModuleSrl($oConfig->connected_review_board_srl);
		$oDocumentModel = getModel('document');
		$category_content = $oDocumentModel->getCategoryPhpFile($oConfig->connected_review_board_srl);
		require($category_content );
		$nLoadReviewCount = 0;
		$aReviews = new stdClass();
		foreach( $oConfig->review_for_item[$item_info->item_srl] as $key=>$val)
		{
			if( $val == 'match' )
			{
				$bExceptNotice = true;
				$bLoadExtraVars = false;
				$args->category_srl = $menu->list[$key]['category_srl'];
				$args->sort_index = $oConfig->order_target?$oConfig->order_target:'list_order';
				$args->order_type = $oConfig->order_type?$oConfig->order_type:'asc';
				//$args->list_count = $oConfig->reviews_per_item;
				$output = $oDocumentModel->getDocumentList($args, $bExceptNotice, $bLoadExtraVars);
				foreach($output->data as $revkey=>$revval)
				{
					if( $revval->variables['status'] == 'TEMP' ) // 임시저장 후기를 숨김
						continue;

					$aReviews->data[$revval->document_srl]->title = $revval->variables['title'];
					$aReviews->data[$revval->document_srl]->member_srl = $revval->variables['member_srl'];
					if($aReviews->data[$revval->document_srl]->member_srl!=0 && $logged_info->member_srl == $aReviews->data[$revval->document_srl]->member_srl)
						$aReviews->data[$revval->document_srl]->isGranted = true;
					else
						$aReviews->data[$revval->document_srl]->isGranted = false;

					$aReviews->data[$revval->document_srl]->nick_name = $revval->variables['nick_name'];
					$aReviews->data[$revval->document_srl]->regdate = $revval->variables['regdate'];
					$aReviews->data[$revval->document_srl]->readed_count = $revval->variables['readed_count'];
					$aReviews->data[$revval->document_srl]->voted_count = $revval->variables['voted_count'];
					$aReviews->data[$revval->document_srl]->blamed_count = $revval->variables['blamed_count'];
					$aReviews->data[$revval->document_srl]->comment_count = $revval->variables['comment_count'];
					$nLoadReviewCount++;
				}
			}
		}
		if(!count($aReviews->data))
			return null;

		$aReviews->mid = $oReviewBoardConfig->mid;
		$aReviews->reviews_per_page = $oConfig->reviews_per_page ? $oConfig->reviews_per_page : 2;
		$aReviews->remaining_reviews = $nCurrentReview - $nLoadReviewCount;
		$aReviews->category_srl = $args->category_srl;

		return $aReviews;
	}
/**
 * @brief provide information about all catalog nodes from cache
 **/
	public function getCatalog($nModuleSrl, $nCatalogSrl)
	{
		if( !$nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_request');

		$sCatalogCacheFile = FileHandler::readFile('./files/cache/svitem/catalog.'.$nModuleSrl.'.'.$nCatalogSrl.'.cache.php');
		if($sCatalogCacheFile)
			return unserialize( $sCatalogCacheFile );
		else
			return $this->_compileCatalogCache($nModuleSrl, $nCatalogSrl);
	}
/**
 * @brief item class로 이동 svpromotion.model.php, svitem.admin.model.php 에서 호출 해결해야 함.
 * @return 
 **/
	public function getOptions( $item_srl )
	{
		$args->item_srl = $item_srl;
		$output = executeQueryArray( 'svitem.getOptions', $args );
		$options = array();
		if( !$output->data )
			return $options;
		foreach ($output->data as $key=>$val)
			$options[$val->option_srl] = $val;
		return $options;
	}
/**
 * @brief Npay EP 연동용 XML 데이터 추출
 * Context::getRequestVars(); 를 사용하지 않고 $_SERVER['QUERY_STRING']를 해석해야 함
 */
	public function getNpayEpListXml()
	{
		// code snippet from npay tech doc - begin
		$query = $_SERVER['QUERY_STRING'];
		$vars = array();
		foreach(explode('&', $query) as $pair) 
		{
			list($key, $value) = explode('=', $pair);
			$key = urldecode($key);
			$value = urldecode($value);
			$vars[$key][] = $value;
		}
		$itemIds = $vars['ITEM_ID'];
		if (count($itemIds) < 1) 
			exit('ITEM_ID 는 필수입니다.');
		// code snippet from npay tech doc - end

		$aExtracted = array();
		$nIdx = 0;
		$db_info = Context::getDBInfo();
		$sServerName = $db_info->default_url;
		
		$oSvitemView = &getView('svitem');
		$oModuleModel = &getModel('module');
		//$oSvpromotionModel = &getModel('svpromotion');
		foreach( $itemIds as $nIdx => $nItemSrl )
		{
			$oArgs->item_srl = $nItemSrl;
			$oItemInfo = $this->_getItemInfo($oArgs);
			$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($oItemInfo->module_srl);
			$sMid = $oModuleInfo->mid;

			$oItemInfo->item_page_url= $sServerName.$sMid.'/'.$oItemInfo->document_srl.'?utm_source=naver&amp;utm_medium=referral&amp;utm_campaign=NV_NS_REF_NPAY_BUY_00&amp;utm_term=npay_buy_'.$oItemInfo->document_srl;
			
			$oItemInfo->item_image = str_replace('https', 'http', $oSvitemView->_dispThumbnailUrl( $oItemInfo->thumb_file_srl, 500, 500, 'crop') );
			$oItemInfo->item_thumbnail = str_replace('https', 'http', $oSvitemView->_dispThumbnailUrl( $oItemInfo->thumb_file_srl, 300, 300, 'crop') );
			$oItemInfo->current_stock = $this->getItemStock($nItemSrl);
			
			$aCategoryInfoByItem = $this->_constructCategoryInfoByItemSrl($oItemInfo->module_srl, $oItemInfo->category_node_srl);
			$oItemInfo->category_1st = $aCategoryInfoByItem[0];
			$oItemInfo->category_2nd = $aCategoryInfoByItem[1];
			$oItemInfo->category_3rd = $aCategoryInfoByItem[2];
			$oItemInfo->category_4th = $aCategoryInfoByItem[3];
			//$oItemInfo->option_info;
			//$oItemInfo->return_info;
			$aExtracted[] = $oItemInfo;
		}
		return $aExtracted;
	}
/**
 * @brief naver EP 연동용 데이터 추출
 */
	public function getNaverEpList()
	{
		$oSvitemConfig = $this->getModuleConfig();
		if($oSvitemConfig->naver_ep_use != 'Y')
			return;
		
		$oSvorderModel = &getModel('svorder');
		$oSvorderConfig = $oSvorderModel->getModuleConfig();
		$nDeliveryFee = (int)$oSvorderConfig->delivery_fee;
		$nFreeDeliveryAmnt = (int)$oSvorderConfig->freedeliv_amount;
		$aExtracted = array();
		$nIdx = 0;
		$db_info = Context::getDBInfo();
		$sServerName = $db_info->default_url;
		
		$oSvitemView = &getView('svitem');
		$oModuleModel = &getModel('module');
		$oSvpromotionModel = &getModel('svpromotion');
		foreach( $oSvitemConfig->naver_ep_extract_svitem as $key=>$val)
		{
			$args->module_srl = $val;
			$output = executeQueryArray('svitem.getItemListNaverEp', $args);
			if( !$output->toBool() )
				return $output;
			$item_list = $output->data;
			
			$oRst = $oModuleModel->getModuleInfoByModuleSrl($val);
			$sMid = $oRst->mid;

			foreach( $item_list as $nIdx => $oVal )
				$this->_unserializeItemInfo($oVal);
			$discounted = $oSvpromotionModel->getItemPriceList($item_list);
			foreach($discounted as $key1=>$val1)
			{
				$sSvCampaignCode2nd = $val1->enhanced_item_info->naver_ep_sv_campaign2 ? $val1->enhanced_item_info->naver_ep_sv_campaign2: '00';
				$sSvCampaignCode3rd = $val1->enhanced_item_info->naver_ep_sv_campaign3 ? $val1->enhanced_item_info->naver_ep_sv_campaign3: '00';
				$discounted[$key1]->link = $sServerName.$sMid.'/'.$val1->document_srl.'?utm_source=naver&utm_medium=cpc&utm_campaign=NV_PS_CPC_NVSHOP_'.$sSvCampaignCode2nd.'_'.$sSvCampaignCode3rd.'&utm_term=NVSHOP_'.$val1->document_srl;
				$discounted[$key1]->thumbnail_url = str_replace('https', 'http', $oSvitemView->_dispThumbnailUrl( $val1->thumb_file_srl, 300, 300, 'crop') );

				if( $val1->price > $nFreeDeliveryAmnt )
					$discounted[$key1]->shipping_cost = 0;
				else
					$discounted[$key1]->shipping_cost = $nDeliveryFee;

				if( strlen( $val1->enhanced_item_info->naver_ep_item_name ) == 0 )
				{
					$discounted[$key1]->bPrintOut = false;
					continue;
				}
				else
				{
					if( strlen( $val1->enhanced_item_info->naver_ep_naver_category ) == 0 )
						$discounted[$key1]->enhanced_item_info->naver_ep_naver_category = ' ';
					if( strlen( $val1->enhanced_item_info->naver_ep_barcode ) == 0 )
						$discounted[$key1]->enhanced_item_info->naver_ep_barcode = ' ';
					if( strlen( $val1->enhanced_item_info->naver_ep_search_tag ) == 0 )
						$discounted[$key1]->enhanced_item_info->naver_ep_search_tag = ' ';

					$nReview = $this->getReviewCnt($val1->item_srl);
					$discounted[$key1]->review_cnt = $nReview;
					$discounted[$key1]->bPrintOut = true;
				}
			}
//var_dump( $discounted);
			// 배열 결합
			foreach($discounted as $key2=>$val2)
			{
				if( $val2->bPrintOut )
					$aExtracted[$nIdx++] = $val2;
			}
		}
		return $aExtracted;
	}
/**
 * @brief Daum EP 연동용 데이터 추출
 */
	public function getDaumEpList()
	{
		$oSvitemConfig = $this->getModuleConfig();
		if($oSvitemConfig->daum_ep_use != 'Y')
			return;
		
		$oSvorderModel = &getModel('svorder');
		$oSvorderConfig = $oSvorderModel->getModuleConfig();
		$nDeliveryFee = (int)$oSvorderConfig->delivery_fee;
		$nFreeDeliveryAmnt = (int)$oSvorderConfig->freedeliv_amount;

		$aExtracted = array();
		$nIdx = 0;
		$sServerName = 'http://'.$_SERVER['SERVER_NAME'];
		foreach( $oSvitemConfig->daum_ep_extract_svitem as $key=>$val)
		{
			$args->module_srl = $val;
			$args->display='Y';
			$output = executeQueryArray('svitem.getItemListNaverEp', $args);
			if( !$output->toBool() )
				return $output;
			$item_list = $output->data;

			$oModuleModel = &getModel('module');
			$oRst = $oModuleModel->getModuleInfoByModuleSrl($val);
			$sMid = $oRst->mid;
			$oSvpromotionModel = &getModel('svpromotion');
			$discounted = $oSvpromotionModel->getItemPriceList($item_list);
			foreach($discounted as $key1=>$val1)
			{
				$oCatalog = $this->getCatalog($val1->module_srl, $val1->category_node_srl );
				$val1->item_name = iconv('UTF-8', 'EUC-KR', $val1->item_name);
				$val1->catalog_name = iconv('UTF-8', 'EUC-KR', $oCatalog->current_catalog_info->node_name);
				$val1->enhanced_item_info->daum_ep_item_name = iconv('UTF-8', 'EUC-KR', $val1->enhanced_item_info->daum_ep_item_name );
				$val1->enhanced_item_info->ga_category_name = iconv('UTF-8', 'EUC-KR', $val1->enhanced_item_info->ga_category_name );
				$val1->enhanced_item_info->ga_brand_name = iconv('UTF-8', 'EUC-KR', $val1->enhanced_item_info->ga_brand_name );
				$val1->enhanced_item_info->naver_ep_maker = iconv('UTF-8', 'EUC-KR', $val1->enhanced_item_info->naver_ep_maker );
				$val1->enhanced_item_info->naver_ep_origin = iconv('UTF-8', 'EUC-KR', $val1->enhanced_item_info->naver_ep_origin );
				$discounted[$key1]->link = $sServerName.'/'.$sMid.'/'.$val1->document_srl.'?utm_source=daum&utm_medium=cpc&utm_campaign=DAUM_PS_CPC_DMSHOP_00_00&utm_term=DMSHOP_'.$val1->document_srl;

				if( $val1->price > $nFreeDeliveryAmnt )
					$discounted[$key1]->shipping_cost = 0;
				else
					$discounted[$key1]->shipping_cost = $nDeliveryFee;
				
				if( strlen( $val1->enhanced_item_info->naver_ep_naver_category ) == 0 )
					$discounted[$key1]->enhanced_item_info->naver_ep_naver_category = ' ';

				$nReview = $this->getReviewCnt($val1->item_srl);
				$discounted[$key1]->review_cnt = $nReview;
			}
			// 배열 결합
			foreach($discounted as $key2=>$val2) 
				$aExtracted[$nIdx++] = $val2;
		}
		return $aExtracted;
	}
/**
 * @brief return module name in sitemap
 **/
	public function triggerModuleListInSitemap(&$obj)
	{
		array_push($obj, 'svitem');
	}
/**
 * @brief will be deprecated 
 * $oArgs should have [item_srl] or [document_srl] or [item_code]
 * item class로 구현해야 함
 * @return 
 **/
	private function _getItemInfo($oArgs) 
	{
		if( !isset( $oArgs) )
			return null;
		$oItemInfo = executeQuery('svitem.getItemInfo', $oArgs);
		if (!$oItemInfo->toBool())
			return null;
		
		if( !$oItemInfo->data->module_srl )
			return null;

		$this->_unserializeItemInfo($oItemInfo);
		return $oItemInfo->data;
	}
/**
 * @brief 등록 상품의 카테고리 정보 구성 -> item_class로 이동
 * item class로 구현해야 함
 */
	private function _constructCategoryInfoByItemSrl($nModuleSrl, $nCategoryNodeSrl) 
	{
		$aItemCategoryInfo = array();
		if( $nModuleSrl && $nCategoryNodeSrl )
		{
			$oCategoryInfo = $this->getCatalog( $nModuleSrl, $nCategoryNodeSrl );
			foreach( $oCategoryInfo->parent_catalog_list as $nCategorySrl => $oCategoryVal )
			{
				if($oCategoryVal->node_srl > 0)
					$aItemCategoryInfo[] = $oCategoryVal->node_name;
			}
			$aItemCategoryInfo[] = $oCategoryInfo->current_catalog_info->node_name;
		}
		return $aItemCategoryInfo;
	}
/**
 * @brief will be deprecated 
 * @return 
 **/
	private function _unserializeItemInfo(&$oItemInfo) 
	{
		if( isset( $oItemInfo->data ) )
		{
			$oItemInfo->data->enhanced_item_info = unserialize( $oItemInfo->data->enhanced_item_info );
			$oItemInfo->data->extra_var_objs = unserialize( $oItemInfo->data->extra_vars ); // skin 명령어에 맞추기 위한 비효율 작업

			unset( $oItemInfo->data->extra_vars ); // skin 명령어에 맞추기 위한 비효율 작업
		}
		else
		{
			$oItemInfo->enhanced_item_info = unserialize( $oItemInfo->enhanced_item_info );
			$oItemInfo->extra_var_objs = unserialize( $oItemInfo->extra_vars ); // skin 명령어에 맞추기 위한 비효율 작업
			unset( $oItemInfo->extra_vars ); // skin 명령어에 맞추기 위한 비효율 작업
		}
	}
/**
 * ShowWindow에 등록된 상품정보 목록을 가져옴
 * @param $module_srl (필수 입력) 가져올 대상 모듈 일련번호, 2개 이상일 땐 array형식으로 넘겨줘야함
 * @param $category_srl 가져올 대상 카테고리 일련번호 (미입력시 전체 카테고리를 대상으로 함)
 * @param $num_columns 가져올 레코드 수
 */
	private function _getWidgetShowWindowDisplayItems($nModuleSrl, $nCatalogSrl=null, $num_columns=null) 
	{
		$sCatalogItemListCacheFile = FileHandler::readFile('./files/cache/svitem/showwindow_itemlist.'.$nModuleSrl.'.'.$nCatalogSrl.'.cache.php');
		if($sCatalogItemListCacheFile)
			$aDisplayCategory = unserialize( $sCatalogItemListCacheFile );
		else
			$aDisplayCategory = $this->_compileShowWindowItemListCache($nModuleSrl, $nCatalogSrl, $num_columns);
		$oSvpromotionModel = &getModel('svpromotion');
		foreach ($aDisplayCategory as $key => $val) 
		{
			if ($val->items) 
				$oRst = $oSvpromotionModel->getItemPriceList($val->items);
		}
		return $aDisplayCategory;
	}
/**
 * ShowWindow에 등록된 상품정보 목록을 가져옴
 */
	private function _compileShowWindowItemListCache($nModuleSrl, $nCatalogSrl=null, $num_columns=null) 
	{
		$args->module_srl = $nModuleSrl;
		if ($nCatalogSrl) 
			$args->category_srl = $nCatalogSrl;
		$output = executeQueryArray('svitem.getShowWindowCatalogList', $args);
		$aDisplayCategory = $output->data;
		if( count( $aDisplayCategory ) ) 
		{
			$oModuleModel = &getModel('module');
			$oSvitemModuleConfig = $oModuleModel->getModuleInfoByModuleSrl($nModuleSrl);
			foreach ($aDisplayCategory as $key => $val) 
			{
				$args->category_srl = $val->category_srl;
				$args->list_count = $num_columns;
				$output = executeQueryArray('svitem.getShowWindowItems', $args);
				if (!$output->toBool())
					return $output;
				foreach( $output->data as $itemkey=>$itemval)
				{
					$this->_unserializeItemInfo($itemval);
					$output->data[$itemkey]->mid = $oSvitemModuleConfig->mid;
				}
				$val->items = $output->data;
				$aDisplayCategory[$key] = $val;
			}
		}
		$sSerializedRst = serialize( $aDisplayCategory );
		FileHandler::writeFile('./files/cache/svitem/showwindow_itemlist.'.$nModuleSrl.'.'.$nCatalogSrl.'.cache.php', $sSerializedRst);
		return $aDisplayCategory;
	}
/**
 * 등록된 상품정보 목록을 가져옴 cameronShopDisplay widget에서만 호출
 * @param $aModuleSrl 가져올 대상 모듈 일련번호, 2개 이상일 땐 array형식으로 넘겨줘야함, null 일 때 전체 대상
 * @param $category_srl 가져올 대상 카테고리 일련번호
 * @param $nMaxSize 가져올 레코드 수
 */
	private function _getWidgetDefaultCatalogItems($nModuleSrl, $nCatalogSrl, $nMaxSize) 
	{
		// get items
		$items = array();
		$oModuleModel = &getModel('module');
		$oSvitemModuleConfig = $oModuleModel->getModuleInfoByModuleSrl($nModuleSrl);
		$output = $this->extractItemListForListPage($nModuleSrl, 0 );
		foreach ($output->data as $key=>$val) 
		{
			$val->mid = $oSvitemModuleConfig->mid;
			$items[$val->item_srl] = $val;
			if (count($items) >= $nMaxSize)
				break;
		}
		//$oSvpromotionModel = &getModel('svpromotion');
		//$retobj = $oSvpromotionModel->getItemPriceList($items);
		$oTmp->items = $items;
		$aWidgetDisplayCatalogs[] = $oTmp;
		return $aWidgetDisplayCatalogs;
	}
/**
 * @brief extract information about catalog belonged item from DB
 **/
	private function _compileCatalogItemListCache($nModuleSrl, $nCatalogSrl, $oArgs)
	{
		if( !$oArgs )
			return new BaseObject(-1, 'msg_invalid_request');
		$output = executeQueryArray('svitem.getCatalogItemList', $oArgs);
		if (!$output->toBool()) 
			return $output;
		$sSerializedRst = serialize( $output );
		FileHandler::writeFile('./files/cache/svitem/catalog_itemlist.'.$nModuleSrl.'.'.$nCatalogSrl.'.'.$oArgs->page.'.cache.php', $sSerializedRst);
		return $output;
	}
/**
 * @brief extract information about all catalog nodes from DB
 **/
	private function _compileCatalogCache($nModuleSrl, $nCatalogSrl)
	{
		if( !$nModuleSrl )
			return false;
        $args = new stdClass();
		$args->module_srl = $nModuleSrl;
		$output = executeQueryArray('svitem.getCatalogNodeList', $args);
		if(count($output->data)==0)
			return;
		foreach( $output->data as $key=>$val)
		{
			$oItemArgs->module_srl = $nModuleSrl;
			$oItemArgs->category_node_srl = $val->category_node_srl;
			$oItemArgs->display = 'Y';
			$oItemListByCategorySrl = executeQueryArray('svitem.getItemListByCategorySrl', $oItemArgs);
			$nCurCatalogBelongedItemCnt = count($oItemListByCategorySrl->data);
			$output->data[$key]->belonged_item_count = $nCurCatalogBelongedItemCnt; 
		}

		require_once('svitem.catalog.php');
		$oCatalog = new catalogList();
		foreach( $output->data as $key=>$val) // 카탈로그 아이템을 연결리스트로 변환
		{
			$oArgs->node_srl = $val->category_node_srl;
			$oArgs->parent_srl = $val->parent_srl;
			$oArgs->catalog_name = $val->category_name;
			$oArgs->belonged_item_count = $val->belonged_item_count;
			$bRet = $oCatalog->insertNode($oArgs);
		}
		
		$nBelongedItemCount = 0;
		$aDirectChildCatalog = array();
		$aCatalogSrl[] = $nCatalogSrl;
		$bChildrenCatalog = false;
		$oCurNode = $oCatalog->getNodeInfo($nCatalogSrl);
		foreach( $oCurNode->direct_child_node_info as $key=>$val)
		{
			// retrieve breadcrumb of direct descendant catalog begin
			$_oTempNodeInfo = new stdClass();
			$_oTempNodeInfo->child_node_srl = $val->cur_node_srl;
			$_oTempNodeInfo->child_node_name = $val->cur_node_name;
			$_oTempNodeInfo->child_item_cnt = $val->total_belonged_item_cnt;
			$nBelongedItemCount += $val->total_belonged_item_cnt;
			$aDirectChildCatalog[] = $_oTempNodeInfo;
			// retrieve breadcrumb of direct descendant catalog end
			
			// 카테고리 입력되면, category_node_srl 검색을 위한 배열 작성
			foreach( $val->all_children_srls as $seckec=>$secval)
			{
				$aCatalogSrl[] = $secval;
				$bChildrenCatalog = true;
			}
		}
		$oRst = new stdClass();
		$oRst->parent_catalog_list = $oCurNode->parent_node_info;
		$oRst->current_catalog_info = $oCurNode->cur_node_info;
		$oRst->direct_child_catalog_list = $aDirectChildCatalog;
		$oRst->displaying_catalog_srls = $aCatalogSrl;
		$oRst->exists_children_catalog = $bChildrenCatalog;
		$oRst->children_owned_item_count = $nBelongedItemCount;
		$sSerializedRst = serialize( $oRst );
		FileHandler::writeFile('./files/cache/svitem/catalog.'.$nModuleSrl.'.'.$nCatalogSrl.'.cache.php',$sSerializedRst);
		return $oRst;
	}
/**
 * @brief Npay 정보는 svcart를 거쳐서 svorder에 질의함; svitem->svcart->svorder 구조 준수
 * @return 
 **/
/*	public function getNpayScriptBySvcartMid($sSvcartMid, $nItemSrl)
	{
		$nAvailableStock = $this->getItemStock( $nItemSrl);
		$oSvcartModel = &getModel('svcart');
		return $oSvcartModel->getNpayScriptBySvcartMid( $sSvcartMid, 'svitem', $nItemSrl, $nAvailableStock );
	}*/
/**
 * @brief will be deprecated 
 * getting item information using document_srl.
 */
	/*public function getItemInfoByDocumentSrl($nDocumentSrl)
	{
		$oArgs->document_srl = $nDocumentSrl;
		$oItem = $this->_getItemInfo($oArgs);
		return $oItem;
	}*/
/**
 * @brief
 * @return 
 **/
/*	public function getBundlings( $item_srl )
	{
		$args->item_srl = $item_srl;
		$output = executeQueryArray( 'svitem.getBundles', $args );
		$bundles = array();
		if( !$output->data )
			return $options;
		foreach( $output->data as $key=>$val )
			$bundles[$val->bundle_srl] = $val;
		return $bundles;
	}*/
/**
 * @brief
 * @return 
 **/
/*	public function getItemExtraFormList($module_srl, $filter_response = false) 
	{
		global $lang;
		// Set to ignore if a super administrator.
		$logged_info = Context::get('logged_info');

		$this->join_form_list = null;
		if(!$this->join_form_list) 
		{
			// Argument setting to sort list_order column
			$args->sort_index = "list_order";
			$args->module_srl = $module_srl;
			$output = executeQueryArray('svitem.getItemExtraList', $args);

			// NULL if output data deosn't exist
			$join_form_list = $output->data;
			if(!$join_form_list)
				return NULL;

			// Need to unserialize because serialized array is inserted into DB in case of default_value
			if(!is_array($join_form_list))
				$join_form_list = array($join_form_list);
			$join_form_count = count($join_form_list);

			for($i=0;$i<$join_form_count;$i++)
			{
				$join_form_list[$i]->column_name = strtolower($join_form_list[$i]->column_name);

				$extra_srl = $join_form_list[$i]->extra_srl;
				$column_type = $join_form_list[$i]->column_type;
				$column_name = $join_form_list[$i]->column_name;
				$column_title = $join_form_list[$i]->column_title;
				$default_value = $join_form_list[$i]->default_value;

				// Add language variable
				$lang->extend_vars[$column_name] = $column_title;

				// unserialize if the data type if checkbox, select and so on
				if(in_array($column_type, array('checkbox','select','radio'))) 
				{
					$join_form_list[$i]->default_value = unserialize($default_value);
					if(!$join_form_list[$i]->default_value[0]) $join_form_list[$i]->default_value = '';
				} 
				else 
					$join_form_list[$i]->default_value = '';

				$list[$extra_srl] = $join_form_list[$i];
			}
			$this->join_form_list = $list;
		}

		// Get object style if the filter_response is true
		if($filter_response && count($this->join_form_list)) 
		{
			foreach($this->join_form_list as $key => $val) 
			{
				if($val->is_active != 'Y')
					continue;
				unset($obj);
				$obj->type = $val->column_type;
				$obj->name = $val->column_name;
				$obj->lang = $val->column_title;
				if($logged_info->is_admin != 'Y')
					$obj->required = $val->required=='Y'?true:false;
				else
					$obj->required = false;
				$filter_output[] = $obj;

				unset($open_obj);
				$open_obj->name = 'open_'.$val->column_name;
				$open_obj->required = false;
				$filter_output[] = $open_obj;
			}
			return $filter_output;
		}
		// Return the result
		return $this->join_form_list;
	}*/
/**
 * @brief 확장변수 목록과 값을 취합하여 리턴
 */
/*	public function getCombineItemExtras(&$item_info) 
	{
		$extra_vars = new stdclass();
		$output = $this->getSvitemExtraVars('', $item_info->item_srl);
		if($output)
		{
			foreach ($output as $key => $val)
				$extra_vars->{$key} = $val;
		}
		// 변수목록을 읽어온다.
		$extend_form_list = $this->getItemExtraFormList($item_info->module_srl);
		if(!$extend_form_list)
			return;

		// 값 취합
		foreach($extend_form_list as $srl => $item)
		{
			$column_name = $item->column_name;
			$value = $extra_vars->{$column_name};
			$extend_form_list[$srl]->value = $value;

			if($extra_vars->{'open_'.$column_name}=='Y')
				$extend_form_list[$srl]->is_opened = true;
			else
				$extend_form_list[$srl]->is_opened = false;
		}
		return $extend_form_list;
	}*/
/**
 * @brief
 * @return 
 **/
/*	public function getExtraVars($module_srl)
	{
		$args->module_srl = $module_srl;
		$output = executeQueryArray('svitem.getItemExtraList', $args);
		if (!$output->toBool())
			return $output;
		$extra_list = $output->data;
		$extra_args = new StdClass();
		if ($extra_list)
		{
			foreach ($extra_list as $key=>$val)
			{
				//$extra_args->{$val->column_name} = Context::get($val->column_name);
				$value = Context::get($val->column_name);
				if(is_array($value)) 
				{ 
debugPrint($value); 
debugPrint('is_array : ' . implode(',',$value)); 
				}
				$extra_args->{$val->column_name} = new NExtraItem($val->module_srl, $key+1, $val->column_name, $val->column_title, $val->column_type, $val->default_value, $val->description, $val->required, 'N', $value);
			}
		}
		return $extra_args;
	}*/
/**
 * @brief
 * @return 
 **/
/*	public function getSvitemExtraVars($module_name=null, $item_srl=null)
	{
		if(!$module_name && !$item_srl) return;
		else
		{
			if($item_srl)
			{
				$args->item_srl = $item_srl;
				$output = executeQueryArray("svitem.getSvitemExtraVars",$args);
				if($output->data) 
				{
					foreach($output->data as $k => $v)
					{
						$extra_values[$v->name] = $v->value;
					}
					Context::set('extra_values', $extra_values);
				}
				if(!$module_srl) return $extra_values;
			}
//			if($module_name)
//			{
//				$oModel = &getModel($module_name);
//				//$oModel->get.ucfirst($module_name).ExtraVars();
//
//				$output = $oModel->getSvitemExtraVars();
//				//$output = $this->getSvitemInputExtraVars($output);
//				return $output;
//			}
		}
	}*/
/**
 * @brief
 * @return 
 **/
/*	public function getCurrencyInfo()
	{
		// 제품 상세페이지에서 통화기호 표시 안되서 주석 처리
		//$sCurrencyConfigFilePath = _XE_PATH_.'modules/svitem/conf/currency.config.php';
		//if( !FileHandler::exists( $sCurrencyConfigFilePath ) )
		//{
			$currency->currency = 'KRW';
			$currency->as_sign = 'Y';
			$currency->decimals = 0;
		//}
		//else
	//		require_once( $sCurrencyConfigFilePath );

		return $currency;
	}*/
/**
 * @brief
 * @return 
 **/
/*	public function getDefaultDisplayConfig($nModuleSrl, $sPageType) 
	{
		if( !$nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_module_srl');

		if( $sPageType != 'catalog' && $sPageType != 'detail' )
			return new BaseObject(-1, 'msg_invalid_page_type');

		switch( $sPageType )
		{
			case 'catalog':
				$aVirtualVars = $this->_g_aCatalogDefaultDisplay;
				break;
			case 'detail':
				$aVirtualVars = $this->_g_aDetailDefaultDisplay;
				break;
		}
		foreach($aVirtualVars as $key) 
			$aExtraVars[$key] = new ExtraItem($nModuleSrl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);

		// 확장변수 정리
		$aExtraVars = array();
		$form_list = $this->getItemExtraFormList($nModuleSrl);
		if(count($form_list))
		{
			$idx = 1;
			foreach ($form_list as $key => $val)
			{
				$aExtraVars[$val->column_name] = new ExtraItem($nModuleSrl, $idx, $val->column_title, $val->column_name, 'N', 'N', 'N', null);
				$idx++;
			}
		}
		return $aExtraVars;
	}*/
/**
 * @brief
 **/
/*	public function getOutputsOnCatalogPage($module_srl) 
	{
		$oModuleModel = &getModel('module');
		// 저장된 목록 설정값을 구하고 없으면 빈값을 줌.
		$list_config = $oModuleModel->getModulePartConfig('svitem', $module_srl);
		if(!$list_config || !count($list_config))
			$list_config = $this->_g_aCatalogDefaultDisplay;

		// 확장변수 정리
		$extra_vars = array();
		$form_list = $this->getItemExtraFormList($module_srl);
		if(count($form_list))
		{
			$idx = 1;
			foreach ($form_list as $key => $val)
			{
				$extra_vars[$val->column_name] = new ExtraItem($module_srl, $idx, $val->column_title, $val->column_name, 'N', 'N', 'N', null);
				$idx++;
			}
		}
		$ret_arr = array();
		foreach($list_config as $key) 
		{
			if(array_key_exists($key, $extra_vars))
				$ret_arr[$key] = $extra_vars[$key];
			else
				$ret_arr[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
		}
		return $ret_arr;
	}*/
/**
 * @brief
 * @return 
 **/
/*	public function getOutputsOnDetailPage($module_srl) 
	{
		$oModuleModel = &getModel('module');
		$aExtraVars = array();

		// 저장된 목록 설정값을 구하고 없으면 빈값을 줌.
		$list_config = $oModuleModel->getModulePartConfig('svitem.detail', $module_srl);
		if(!$list_config || !count($list_config))
			$list_config = $this->_g_aDetailDefaultDisplay;	

		// 확장변수 정리
		$form_list = $this->getItemExtraFormList($module_srl);
		if(count($form_list))
		{
			$idx = 1;
			foreach ($form_list as $key => $val)
			{
				$aExtraVars[$val->column_name] = new ExtraItem($module_srl, $idx, $val->column_title, $val->column_name, 'N', 'N', 'N', null);
				$idx++;
			}
		}
		$ret_arr = array();
		foreach($list_config as $key) 
		{
			if(array_key_exists($key, $aExtraVars))
				$ret_arr[$key] = $aExtraVars[$key];
			else
				$ret_arr[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
		}
		return $ret_arr;
	}*/
/**
 * @param $item_srl 데이터 가져올 item_srl (2개 이상일 때 array로)
 * dispSvitemItemDetail()에서 related_item 호출에서 사용
 */
	/*public function getItemListByItemSrls($item_srl, $list_count=20, $sort_index='list_order') 
	{
		$config = $this->getModuleConfig();
		$args->item_srl = $item_srl;
		$args->list_count = $list_count;
		$args->sort_index = $sort_index;
		$output = executeQueryArray('svitem.getItemListByItemSrls', $args);
		if (!$output->toBool())
			return;
		$list = array();
		foreach($output->data as $no=>$val)
			$list[] = null;//new svit1emItem($val, $config->currency, $config->as_sign, $config->decimals);
		
		return $list;
	}*/
/**
 * @brief 
 * @return 
 **/
	/*function getDefaultListConfig($module_srl) 
	{
		$extra_vars = array();
		// 체크박스, 이미지, 상품명, 수량, 금액, 주문 추가
		$virtual_vars = array('checkbox', 'image', 'title', 'quantity', 'amount', 'cart_buttons', 'sales_count' );//, 'download_count');
		foreach($virtual_vars as $key) 
			$extra_vars[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);

		// 확장변수 정리
		$form_list = $this->getItemExtraFormList($module_srl);
		if(count($form_list))
		{
			$idx = 1;
			foreach ($form_list as $key => $val)
			{
				$extra_vars[$val->column_name] = new ExtraItem($module_srl, $idx, $val->column_title, $val->column_name, 'N', 'N', 'N', null);
				$idx++;
			}
		}
		return $extra_vars;
	}*/
}
/* End of file svitem.model.php */
/* Location: ./modules/svitem/svitem.model.php */