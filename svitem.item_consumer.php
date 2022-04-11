<?php
/**
 * @class  svitemItemConsumer
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemItemConsumer class
 */
class svitemItemConsumer extends svitem
{
	private $_g_sArchivePath = NULL;
	private $_g_oSvitemModuleConfig = NULL; // svitem module config 적재
	private $_g_oLoggedInfo = NULL;
	private $_g_oOldItemHeader = NULL; // 항상 현재 읽기의 기준
	private $_g_oNewItemHeader = NULL; // 미래 상태에 관한 참조일 뿐
	private $_g_oBarcodeInfo = NULL;
	const A_ITEM_HEADER_TYPE = ['_g_oNewItemHeader', '_g_oOldItemHeader'];
/**
 * @brief 생성자
 * $oParams->oSvitemConfig
 **/
	public function __construct($oParams=null) 
	{
		$this->_g_sArchivePath = _XE_PATH_.'files/svitem/';
		$this->_g_oLoggedInfo = Context::get('logged_info');
		if($oParams->oSvitemModuleConfig)
			$this->_g_oSvitemModuleConfig = $oParams->oSvitemModuleConfig;
		$this->_setSkeletonSvitemHeader();
	}
/**
 * @brief 품목 기본 정보 적재
 * $oParams->mode == 'import' 카탈로그 화면 로드 시 DB 처리 효율을 위해 상품 목록을 한번에 가져오는 경우 처리
 * $oParams->mode == 'import' 면 $oParams->oRawData is mandatory
 **/
	public function loadHeader($oParams)
	{
		switch($oParams->mode)
		{
			case 'import':
				if(!$oParams->oRawData)
					return new BaseObject(-1,'msg_import_load_without_rawdata');
				$this->_initHeader();
				$oTmpRst = new BaseObject();
				$oTmpRst->data = $oParams->oRawData;
				break;
			default:
                $oTmpArgs = new stdClass();
				if($oParams->nItemSrl)
					$oTmpArgs->item_srl = $oParams->nItemSrl;
				elseif($oParams->nDocumentSrl)
					$oTmpArgs->document_srl = $oParams->nDocumentSrl;
				elseif($oParams->item_code)
					$oTmpArgs->item_code = $oParams->item_code;

				$oTmpRst = executeQuery('svitem.getItemInfo', $oTmpArgs);
				if(!$oTmpRst->toBool())
					return $oTmpRst;
				unset($oTmpArgs);
				break;
		}
		//unset( $oTmpRst->data->extra_vars ); // svitem_item.xml 수정하면 제거
		if( $oTmpRst->data->enhanced_item_info )
			$oTmpRst->data->enhanced_item_info = unserialize( $oTmpRst->data->enhanced_item_info );

		// new badge 표시 여부
		$dtReg = new DateTime($oTmpRst->data->regdate); // new badge 표시 여부 시작 
		$dtToday = new DateTime(date('Ymdhis'));
		$dtElapsedDays = date_diff($dtReg, $dtToday);
		if( $dtElapsedDays->days < 30 ) // 등록 후 30일까지만 new 표시
			$oTmpRst->data->enhanced_item_info->default_badge_icon['new']=1;
		
		$this->_matchOldItemInfo($oTmpRst->data);
		//$this->_parseBarcode();

		unset($oTmpRst->data);
		
		$this->_consturctExtraVars(); // extra_var 설정
		$this->_setItemStock(); // 재고수 설정
		return new BaseObject();
	}
/**
 * @brief 품목 상세 정보 적재, detail page 전용
 **/
	public function loadDetail()
	{
		if( $this->_g_oOldItemHeader->category_node_srl > 0 )
		{
 //////////////////////// getCatalog() 가져오기
			$oSvitemModel = &getModel('svitem');
			$nModuleSrl = $this->_g_oOldItemHeader->module_srl;
			$this->_g_oOldItemHeader->oCatalog = $oSvitemModel->getCatalog($nModuleSrl, $this->_g_oOldItemHeader->category_node_srl);
 ////////////////////////
			if( strlen( $this->_g_oOldItemHeader->enhanced_item_info->ga_category_name ) == 0 )
				$this->_g_oOldItemHeader->enhanced_item_info->ga_category_name = $this->_g_oOldItemHeader->oCatalog->current_catalog_info->node_name;
			unset( $oSvitemModel );
		}
		
		if( $this->_g_oOldItemHeader->gallery_doc_srl > 0 )
		{
			$oFileModel = getModel('file');
			$aGalleryImg = $oFileModel->getFiles($this->_g_oOldItemHeader->gallery_doc_srl, array(), 'file_srl', true);
			if(count((array)$aGalleryImg))
			{
				$aAllowdFileExtension = [ 'GIF', 'JPG', 'PNG', 'BMP', 'TIFF' ];
				foreach( $aGalleryImg as $nIdx => $oFile )
				{
					$aFileName = explode('.', $oFile->source_filename);
					$nChunk = count($aFileName);
					$sFileExt = strtoupper( $aFileName[$nChunk-1] );
					if( in_array($sFileExt, $aAllowdFileExtension ) )
						$aGallery[] = $oFile->uploaded_filename;
				}
			}
			unset($aGalleryImg);
			if( $this->_g_oOldItemHeader->enhanced_item_info->rep_gallery_thumb_idx > 0 ) // 대표 썸네일 번호 지정이라면
			{
				$aTmpGallery = [];
				$aTmpGallery[] = $aGallery[$nRepGalleryThumbIdx];
				unset($aGallery[$nRepGalleryThumbIdx]);
				foreach( $aGallery as $nIdx => $sFilename )
					$aTmpGallery[] = $sFilename;
				$aGallery = $aTmpGallery;
			}
			$this->_g_oOldItemHeader->aGalleryImg = $aGallery;
			unset($oFileModel);
		}

		// for detail description
		if(	Mobile::isMobileCheckByAgent() )
		{
			if( $this->_g_oOldItemHeader->enhanced_item_info->description_skin_mob )
			{
				$oTemplate = &TemplateHandler::getInstance();
				$this->_g_oOldItemHeader->mob_description = $oTemplate->compile($this->_g_sArchivePath, $this->_g_oOldItemHeader->enhanced_item_info->description_skin_mob); 
			}
			else
			{
				if( strpos($this->_g_oOldItemHeader->mob_description, '%%PC%%') !== false )
					$this->_g_oOldItemHeader->mob_description = $this->_g_oOldItemHeader->pc_description;
			}
			if( strlen($this->_g_oOldItemHeader->mob_description) == 0 ) // 최종적으로 아무 내용도 설정되지 않았으면
				$this->_g_oOldItemHeader->mob_description = 'Please define mob descrtion!';
			unset($this->_g_oOldItemHeader->pc_description ); // to save memory
		}
		else
		{
			if( $this->_g_oOldItemHeader->enhanced_item_info->description_skin_pc )
			{
				$oTemplate = &TemplateHandler::getInstance();
				$this->_g_oOldItemHeader->pc_description = $oTemplate->compile($this->_g_sArchivePath, $this->_g_oOldItemHeader->enhanced_item_info->description_skin_pc); 
			}
			else
			{
				if( strpos($this->_g_oOldItemHeader->pc_description, '%%MOB%%') !== false )
					$this->_g_oOldItemHeader->pc_description = $this->_g_oOldItemHeader->mob_description;
			}
			if( strlen($this->_g_oOldItemHeader->pc_description) == 0 ) // 최종적으로 아무 내용도 설정되지 않았으면
				$this->_g_oOldItemHeader->pc_description = 'Please define pc description!';
			unset($this->_g_oOldItemHeader->mob_description ); // to save memory
		}

		// for sns share
		$oDocumentModel = &getModel('document');
		$oDocument = $oDocumentModel->getDocument($this->_g_oOldItemHeader->document_srl);
		
		if( $this->_g_oOldItemHeader->enhanced_item_info->item_brief == '%%OG%%' )
			$this->_g_oOldItemHeader->enhanced_item_info->item_brief = $oDocument->getContent(false);

		$oDbInfo = Context::getDBInfo();
        $oSnsInfo = new stdClass();
		$oSnsInfo->sPermanentUrl = $oDocument->getPermanentUrl().'?l='.$oDbInfo->lang_type;
		$oSnsInfo->sEncodedDocTitle = urlencode($this->_g_oOldItemHeader->item_name);
		$this->_g_oOldItemHeader->oSnsInfo = $oSnsInfo;
		unset( $oDbInfo );
		unset( $oDocument );
		unset( $oDocumentModel );

		//bundling_info begin
		$nTypeId = 1;
		$oBundlingRst = $this->_getBundlings();
		$aBundling = $oBundlingRst->get('aBundle');
		foreach( $aBundling as $nIdx => $oRec )
		{
			$aFloatingBundling = explode( ',', $oRec->bundle_items);
			$nBundleCnt = count($aFloatingBundling);
			if( $nBundleCnt > 1 )
			{
				$nFloatingIdx = 0;
				$aFloatingBundling = explode( ',', $sBundleItems );
				foreach($aFloatingBundling as $nIdx => $nItemSrl )
				{
					$oRst = $this->_getSimpleItemInfoByItemSrl($nItemSrl);
					$aFloatingProduct[$nFloatingIdx]->nItemSrl = $nItemSrl;
					$aFloatingProduct[$nFloatingIdx]->sItemName = $oRst->data->item_name;
					$aFloatingProduct[$nFloatingIdx]->nItemPrice = $oRst->data->price;
					$aFloatingProduct[$nFloatingIdx++]->nTypeid = $nTypeId;
					unset($oRst);
				}
				$aMaxQty[$nTypeId] = $oRec->bundle_quantity;
				$nTypeId++; 
			}
			elseif( $nBundleCnt == 1 )
			{
				$nFixedIdx = 0;
				$oRst = $this->_getItemInfoByItemSrl($oRec->bundle_items);
				$aFixedProduct[$nFixedIdx]->nItemSrl = $oRst->bundle_items;
				$aFixedProduct[$nFixedIdx]->sItemName = $oRst->data->item_name;
				$aFixedProduct[$nFixedIdx]->nItemPrice = $oRst->data->price;
				$aFixedProduct[$nFixedIdx]->nMaxQty = $oRec->bundle_quantity;
				$aFixedProduct[$nFixedIdx++]->nTypeid = $nTypeId;
				$nTypeId++;
				unset($oRst);
			}
		}
		if(count((array)$aFloatingProduct)) // multi bundling
		{
			$this->_g_oOldItemHeader->aFloatingProduct = $aFloatingProduct;
			$this->_g_oOldItemHeader->aMaxQty = $aMaxQty; 
		}
		if(count((array)$aFixedProduct)) // single bundling
			$this->_g_oOldItemHeader->aFixedProduct = $aFixedProduct;
		//bundling_info end
		// get buying options
		$this->_setBuyingOption();
		return new BaseObject();
	}
/**
 * @brief impression patch
 **/
	public function patchImpression($oParams=null)
	{
		// $_SESSION에 catalog 거쳤는지 표시
		// pass through impression vs standalone imppression of detail page
        $oParams = new stdClass();
		$oParams->location = 'catalog';//'detail'//'cart'//'order_complete';
		$oParams->postion = 1;// 'catalog'일 때 진열 순서
		$oParams->session = 'user_Session_id';
		//echo __FILE__.':'.__lINE__.':'.$this->_g_oOldItemHeader->item_name.' impression patched<BR>';
	}
/**
 * @brief 카탈로그 용 품목별 프로모션 정보 적용
 * 처리 효율을 위해 외부에서 생성한 oSvpromotionModel을 주소 참조로 사용함
 **/
	public function applyPromotionForCatalog(&$oSvpromotionModel)
	{
		$oPromoRst = $oSvpromotionModel->getItemPriceDetail( $this->_g_oOldItemHeader );
		$this->_g_oOldItemHeader->discounted_price = $oPromoRst->discounted_price;
		$this->_g_oOldItemHeader->discount_amount = $oPromoRst->discount_amount;
		$this->_g_oOldItemHeader->discount_info = $oPromoRst->discount_info;
		// sale badge 표시 여부
		if( $oTmpRst->data->discount_amount )
			$oTmpRst->data->enhanced_item_info->default_badge_icon['sale']=1; 

//		if( $this->_g_oNewItemHeader->quantity > 0 )
//		{
//			$this->_g_oOldItemHeader->sum_discount_amount = $oPromoRst->discount_amount * $this->_g_oNewItemHeader->quantity;
//			$this->_g_oOldItemHeader->sum_discounted_price = $oPromoRst->discounted_price * $this->_g_oNewItemHeader->quantity;
//			$this->_g_oOldItemHeader->sum_price = $this->_g_oNewItemHeader->price * $this->_g_oNewItemHeader->quantity;
//		}
		unset($oPromoRst);
		return new BaseObject();
	}
/**
 * @brief 상세페이지 용 품목별 프로모션 정보 적용
 **/
	public function applyPromotionForDetail()
	{
		// 아이템별 기본 할인 정책 가져오기
        $oInfo4Promotion = new stdClass();
		$oInfo4Promotion->module_srl = $this->_g_oOldItemHeader->module_srl;
		$oInfo4Promotion->item_srl = $this->_g_oOldItemHeader->item_srl;
		$oInfo4Promotion->price = $this->_g_oOldItemHeader->price;
		$oInfo4Promotion->quantity = $this->_g_oOldItemHeader->quantity;

		$oSvpromotionModel = &getModel('svpromotion');
		$aPromoRst = $oSvpromotionModel->getPromotionInfoForItemDetailPage($oInfo4Promotion);
		unset($oInfo4Promotion);
		unset($oSvpromotionModel);
		$this->_g_oOldItemHeader->discounted_price = $aPromoRst['unconditional_disc']->discounted_price;
		$this->_g_oOldItemHeader->discount_amount = $aPromoRst['unconditional_disc']->discount_amount;
		$this->_g_oOldItemHeader->discount_info = $aPromoRst['unconditional_disc']->discount_info ? $aPromoRst['unconditional_disc']->discount_info : '';

		// 아이템별 부가 할인 정책 가져오기
		$nConditionalAdditionalDisc = 0;
		$this->_g_oOldItemHeader->additional_conditional_promotion_type = [];
		foreach( $aPromoRst['conditional']->promotion as $key => $val )
		{
			if( $val->conditional_additional_discount_amount > 0 )
			{
				$nConditionalAdditionalDisc += $val->conditional_additional_discount_amount;
				if( $val->conditional_additional_discount_type == 'coupon' )
					$aCouponPromotion[] = $aPromoRst['conditional']->promotion[$key];
				elseif( $val->conditional_additional_discount_type == 'fblike' )
					$this->_g_oOldItemHeader->additional_conditional_discount_info_fblike = $aPromoRst['conditional']->promotion[$key];
				elseif( $val->conditional_additional_discount_type == 'fbshare' )
					$this->_g_oOldItemHeader->additional_conditional_discount_info_fbshare = $aPromoRst['conditional']->promotion[$key];
				$this->_g_oOldItemHeader->additional_conditional_promotion_type[$val->conditional_additional_discount_type] = 'Y';
			}
		}
		if( $nConditionalAdditionalDisc > 0 )
		{
			$this->_g_oOldItemHeader->fb_app_id = $aPromoRst['conditional']->fb_app_id;
			$this->_g_oOldItemHeader->additional_conditional_discount_amount = $nConditionalAdditionalDisc;
			if( count($aCouponPromotion) > 0 )
				$this->_g_oOldItemHeader->additional_conditional_discount_info_coupon = $aCouponPromotion;
		}
		unset($aPromoRst);
		return new BaseObject;
	}
/**
 * @brief
 **/
	public function __get($sName) 
	{
		if( $sName == 'nModuleSrl' )
			return $this->_g_oOldItemHeader->module_srl; // [module_srl] attr은 Context 클래스를 통과하면서 전달되지 않는 것 같음

		if( isset($this->_g_oOldItemHeader->{$sName}) )
		{
			if( $this->_g_oOldItemHeader->{$sName} == svitem::S_NULL_SYMBOL )
				return null;
			else
				return $this->_g_oOldItemHeader->{$sName};
		}
		else
		{
			if( $sName != 'aGalleryImg' ) // 초기 설정 시 썸네일 전혀 없으면 예외 발생 방지
			{
				debugPrint($sName);
				trigger_error('Undefined property or method: '.$sName);
			}
		}
	}
/**
 * @brief
 **/
 	public function __set($name, $value) 
	{
		if ( property_exists($this, $name) ) 
		{
			$this->{$name} = $value;
			return;
		}
		$method_name = "set_{$name}";
		if ( method_exists($this, $method_name) ) 
		{
			$this->{$method_name}($value);
			return;
		}
	    trigger_error("Undefined property $name or method $method_name");
	}
/**
 * @brief 기존 품목 정보 변경
 **/
	public function update($oItemArgs)
	{
		return;
		if( !$this->_g_oOldItemHeader )
			return new BaseObject(-1,'msg_required_to_load_old_information_first');

		$this->_matchSvitemInfo($oItemArgs);
		if( $this->_g_oNewItemHeader->item_srl == svitem::S_NULL_SYMBOL )
			return new BaseObject(-1,'msg_invalid_request');
		
		// 고정값은 외부 쿼리로 변경하지 않음
		$this->_g_oNewItemHeader->document_srl = $this->_g_oOldItemHeader->document_srl;
		$this->_g_oNewItemHeader->mob_doc_srl = $this->_g_oOldItemHeader->mob_doc_srl;
		$this->_g_oNewItemHeader->pc_doc_srl = $this->_g_oOldItemHeader->pc_doc_srl;
		$this->_g_oNewItemHeader->gallery_doc_srl = $this->_g_oOldItemHeader->gallery_doc_srl;
		//return $this->_updateItem();
	}
/**
 * @brief svitem 스킨에서 호출하는 메쏘드
 */	
	public function getThumbnailUrl( $nWidth = 80, $nHeight = 0, $sThumbnailType = 'crop' )
	{
		$sNoimgUrl = Context::getRequestUri().'/modules/svitem/tpl/img/no_img_80x80.jpg';
		if($this->_g_oOldItemHeader->thumb_file_srl == svitem::S_NULL_SYMBOL || is_null( $this->_g_oOldItemHeader->thumb_file_srl ) ) // 기본 이미지 반환
			return $sNoimgUrl;
		
		if(!$nHeight)
			$nHeight = $nWidth;
		
		// Define thumbnail information
		$sThumbnailPath = 'files/cache/thumbnails/'.getNumberingPath($this->_g_oOldItemHeader->thumb_file_srl, 3);
		$sThumbnailFile = $sThumbnailPath.$nWidth.'x'.$nHeight.'.'.$sThumbnailType.'.jpg';
		$sThumbnailUrl = Context::getRequestUri().$sThumbnailFile;
		// Return false if thumbnail file exists and its size is 0. Otherwise, return its path
		if(file_exists($sThumbnailFile) && filesize($sThumbnailFile) > 1 ) 
			return $sThumbnailUrl;

		// Target File
		$oFileModel = &getModel('file');
		$sSourceFile = NULL;
		$sFile = $oFileModel->getFile($this->_g_oOldItemHeader->thumb_file_srl);
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
	public function getExtraVarTitle($oExtraVars, $sKey)
	{
		if(isset($oExtraVars->{$sKey}->title))
			return $oExtraVars->{$sKey}->title;
		return NULL;
	}
/**
 * @brief svitem 스킨에서 호출하는 메쏘드
 */	
	public function getExtraVarValue($oExtraVars, $sKey)
	{
		$value = NULL;
		if(isset($oExtraVars->{$sKey}->value))
			$value = $oExtraVars->{$sKey}->value;
		if(is_array($value))
			$value = implode(',',$value);
		return $value;
	}
/**
* @brief for debug only
*/
	public function dumpInfo()
	{
		foreach( $this->_g_oNewItemHeader as $sTitle=>$sVal)
		{
			if(is_object($sVal))
			{
				echo $sTitle.'=><BR>';
				var_dump($sVal);
				echo '<BR>';
			}
			else
				echo $sTitle.'=>'.$sVal.'<BR>';
		}
	}
/**
 * @brief barcode 정보 해석
 **/
	private function _parseBarcode()
	{
		if($this->_g_oOldItemHeader->barcode)
		{
			$oSvitemModel = &getModel('svitem');
			$oConfig = $oSvitemModel->getModuleConfig();
			$nPos = 0;
			//$this->_g_oBarcodeInfo->sProductionCostTag = '';
			$sTmpProductionCostTag = '';
			foreach( $oConfig->aDdlDefinition as $nIdx => $oDdl )
			{
				$sValueTitle = $oDdl->title;
				$nLength = $oDdl->length;
				$sChunk = substr($this->_g_oOldItemHeader->barcode, $nPos, $nLength);
				$this->_g_oBarcodeInfo->$sValueTitle = $sChunk;
				if( $oDdl->cost_tag == 'Y' )
					$sTmpProductionCostTag .= $sChunk;
				$nPos += $oDdl->length;
			}
			unset($oSvitemModel);
			unset($oConfig);

			$oArgs->fianance_tag = $sTmpProductionCostTag;
			$oRst = executeQueryArray('svitem.getLatestProductionCostByTag', $oArgs);
			if(!$oRst->toBool())
				return $oRst;
			unset($oArgs);
			$oFinanceInfo = array_shift($oRst->data);
			unset($oRst);
			$this->_g_oBarcodeInfo->fProductionCost = (float)$oFinanceInfo->production_cost;
			$this->_g_oBarcodeInfo->fNormalPrice = (float)$oFinanceInfo->normal_price;
		}
	}
/**
 * @brief 
 * @return 
 **/	
	private function _setBuyingOption()
	{
        $oArgs = new stdClass();
		$oArgs->item_srl = $this->_g_oOldItemHeader->item_srl;
		$oOptionRst = executeQueryArray('svitem.getOptions', $oArgs);
		if(!$oOptionRst->toBool())
			return $oOptionRst;
		unset($oArgs);
		$aButingOptions = [];
		foreach ($oOptionRst->data as $nIdx=>$oOption)
			$aButingOptions[$oOptionRst->option_srl] = $oOptionRst;
		$this->_g_oOldItemHeader->aBuyingOption = $aButingOptions;
	}
/**
 * @brief 
 * @return 
 **/	
	private function _setItemStock()
	{
		$nSafeStockByItem = 0;
		$nSafeStockByItem = (int)$this->_g_oOldItemHeader->safe_stock;
		if( $nSafeStockByItem > 0 )
			$nSafeStock = $nSafeStockByItem;
		else
		{
			//$oConfig = $this->_g_oSvitemModuleConfig = $this->getModuleConfig();
			$nSafeStock = (int)$oConfig->default_safe_stock;
		}
		if( $oConfig->external_server_type == 'ecaso' )
		{
			require_once(_XE_PATH_.'modules/svitem/ext_class/ecaso.class.php');
			$oExtServer = new ecaso( $this->_g_oOldItemHeader->item_code );
			$nCurrentStock = $oExtServer->getStock();
		}
		else
			$nCurrentStock = (int)$this->_g_oOldItemHeader->current_stock;

		if( $nSafeStock >= $nCurrentStock )
			$this->_g_oOldItemHeader->current_stock = 0;
		else 
			$this->_g_oOldItemHeader->current_stock = $nCurrentStock;
	}
/**
 * @brief 귀속된 번들 제품 정보를 간단하게 적재함
 **/
	private function _getSimpleItemInfoByItemSrl($nItemSrl)
	{
		$oArgs->item_srl = $nItemSrl;
		return executeQuery('svitem.getItemInfoSimple', $oArgs);
	}
/**
 * @brief set skeleton svitem header
 * svitem.item_admin.php::_setSkeletonSvitemHeader()과 통일성 유지
 **/
	private function _setSkeletonSvitemHeader()
	{
        $this->_g_oOldItemHeader = new stdClass();
		$this->_g_oOldItemHeader->module_srl = svitem::S_NULL_SYMBOL; // __get 메소드로 접근하면 XE context를 통과하여 전달할 수 없는 거 같음
		$this->_g_oOldItemHeader->document_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->disp_module_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->category_id = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->category_node_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->item_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->gallery_doc_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->mob_doc_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->pc_doc_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->thumb_file_srl = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->list_order = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->item_code = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->barcode = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->item_name = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->price = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->taxfree = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->enhanced_item_info = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->display = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->sales_count = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->current_stock = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->safe_stock = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->description = svitem::S_NULL_SYMBOL; // open graph / pc detail page / mob detail page
		$this->_g_oOldItemHeader->sv_tags = svitem::S_NULL_SYMBOL; // for item search
		$this->_g_oOldItemHeader->extra_vars = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->mob_description = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->pc_description = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->updatetime = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->regdate = svitem::S_NULL_SYMBOL;
		
		// promotion info
		//$this->_g_oOldItemHeader->quantity = svitem::S_NULL_SYMBOL;
		
		// unconditional_disc
		$this->_g_oOldItemHeader->discounted_price = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->discount_amount = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->discount_info = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->sum_discount_amount = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->sum_discounted_price = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->sum_price = svitem::S_NULL_SYMBOL;
		
		// giveaway
		$this->_g_oOldItemHeader->conditional_additional_discount_giveaway_item_name = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->conditional_additional_discount_giveaway_item_price = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->conditional_additional_discount_giveaway_item_url = svitem::S_NULL_SYMBOL;

		// conditional_disc
		$this->_g_oOldItemHeader->fb_app_id = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->additional_conditional_discount_amount = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->additional_conditional_promotion_type = svitem::S_NULL_SYMBOL; // array
		$this->_g_oOldItemHeader->additional_conditional_discount_info_fblike = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->additional_conditional_discount_info_fbshare = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->additional_conditional_discount_info_coupon = svitem::S_NULL_SYMBOL;

		// in-memory attribution for detail page
		$this->_g_oOldItemHeader->oCatalog = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->aGalleryImg = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->oSnsInfo = svitem::S_NULL_SYMBOL;
		$this->_g_oOldItemHeader->aFloatingProduct = svitem::S_NULL_SYMBOL; // multi bundling
		$this->_g_oOldItemHeader->aMaxQty = svitem::S_NULL_SYMBOL; // multi bundling
		$this->_g_oOldItemHeader->aFixedProduct = svitem::S_NULL_SYMBOL; // single bundling
		$this->_g_oOldItemHeader->aBuyingOption = svitem::S_NULL_SYMBOL; // buying option 
		
		// will be deprecated
		$this->_g_oOldItemHeader->related_items = svitem::S_NULL_SYMBOL;

		// temp item info for insertion
		$this->_g_oOldItemHeader->ua_type = svitem::S_NULL_SYMBOL;

		// TBD
		$this->_g_oOldItemHeader->extmall_log_conf = svitem::S_NULL_SYMBOL;
	}
/**
 * @brief 헤더에 extra_vars 설정
 * svitem.item_admin.php::_consturctExtraVars()와 통일성 유지
 **/
	private function _consturctExtraVars()//$oParams)
	{
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
        $oArg = new stdClass();
		$oArg->nItemSrl = $this->_g_oOldItemHeader->item_srl;
		$oArg->nModuleSrl = $this->_g_oOldItemHeader->module_srl;
		$this->_g_oOldItemHeader->extra_vars = $oExtraVarsController->getExtendedVarsNameValueByItemSrl($oArg);
//		foreach( self::A_ITEM_HEADER_TYPE as $nTypeIdx => $sHeaderType )
//		{
//			foreach( $aExtraVar as $nIdx => $oExtraVar )
//			{
//				$sAttrName = $oExtraVar->name;
//				$this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
//			}
//		}
		unset($oArg);
		unset($oExtraVarsController);
//echo __FILE__.':'.__lINE__.'<BR>';
//var_dump( $this->_g_oOldItemHeader->extra_vars);
//echo '<BR><BR>';
	}
/**
 * @brief
 * @return 
 **/
	private function _getBundlings()
	{
        $oArgs = new stdClass();
		$oArgs->item_srl = $this->_g_oOldItemHeader->item_srl;
		$oRst = executeQueryArray( 'svitem.getBundles', $oArgs );
		$aBundle = [];
		if(!$oRst->data)
			return $oRst;
		foreach( $oRst->data as $nIdx=>$oReoRec )
			$aBundle[$val->bundle_srl] = $val;

		$oRst->add('aBundle', $aBundle);
		return $oRst;
	}
/**
 * @brief 'related_items', 'extmall_log_conf' 완전 제거
 **/
	private function _matchOldItemInfo($oOldItemArgs)
	{	
		$aIgnoreVar = array('module', 'mid', 'act', 'quantity', 'related_items', 'extmall_log_conf', '__related_items', '__extra_vars' );
		foreach( $oOldItemArgs as $sTitle => $sVal)
		{
			if(in_array($sTitle, $aIgnoreVar)) 
				continue;

			if( $this->_g_oOldItemHeader->$sTitle == svitem::S_NULL_SYMBOL )
				$this->_g_oOldItemHeader->$sTitle = $sVal;
			else
			{
//////////////// for debug only
				if( is_object( $sVal ) )
				{
					var_dump( 'weird: '.$sTitle );
					echo '<BR>';
					var_dump( $sVal );
					echo '<BR>';
				}
				else
				{
					var_dump( 'weird: '.$sTitle.' => '. $sVal );
					echo '<BR>';
				}
//////////////// for debug only
			}
		}
	}
/**
 * @brief 저장 명령을 실행하기 위해 값 할당 후에도 svitem::S_NULL_SYMBOL이면 null로 변경
 **/
	private function _nullifyHeader()
	{
		foreach( self::A_ITEM_HEADER_TYPE as $nTypeIdx => $sHeaderType )
		{
			foreach( $this->{$sHeaderType} as $sTitle => $sVal)
			{
				if( $sVal == svitem::S_NULL_SYMBOL )
					$this->{$sHeaderType}->$sTitle = null;
			}
		}
	}
/**
 * @brief 헤더 초기화
 **/
	private function _initHeader()
	{
		foreach( $this->_g_oNewItemHeader as $sTitle => $sVal)
			$this->_g_oNewItemHeader->$sTitle = svitem::S_NULL_SYMBOL;
		foreach( $this->_g_oOldItemHeader as $sTitle => $sVal)
			$this->_g_oOldItemHeader->$sTitle = svitem::S_NULL_SYMBOL;
	}
}
/* End of file svitem.item_consumer.php */
/* Location: ./modules/svitem/svitem.item_consumer.php */