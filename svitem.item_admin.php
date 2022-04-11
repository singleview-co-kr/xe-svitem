<?php
/**
 * @class  svitemItemAdmin
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemItemAdmin class
 */
class svitemItemAdmin extends svitem
{
	private $_g_oSvitemModuleConfig = NULL; // svitem module config 적재
	private $_g_oLoggedInfo = NULL;
	private $_g_oOldItemHeader = NULL; // 정보 수정할 때 과거 상태에 관한 참조일 뿐
	private $_g_oNewItemHeader = NULL; // 항상 현재 쓰기의 기준
	const A_ITEM_HEADER_TYPE = ['_g_oNewItemHeader', '_g_oOldItemHeader'];
/**
 * @brief 생성자
 * $oParams->oSvitemConfig
 **/
	public function __construct(&$oParams=null)
	{
		$this->_g_oLoggedInfo = Context::get('logged_info');
		if($oParams->oSvitemModuleConfig)
			$this->_g_oSvitemModuleConfig = $oParams->oSvitemModuleConfig;
		$this->_setSkeletonHeader();
	}
/**
 * @brief 
 **/
	public function __get($sName) 
	{
		if($sName == 'nModuleSrl')
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
			debugPrint($sName);
			trigger_error('Undefined property or method: '.$sName);
		}
//		if ( method_exists($this, $sName) )
//			return $this->{$sName}();
	}
/**
 * @brief
 **/
 	public function __set($name, $value) 
	{
		if( property_exists($this, $name) ) 
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
 * @brief 신규 품목 생성
 **/
	public function create($oNewItemArgs, $sMode='single')
	{
		$this->_initHeader();
		$this->_matchNewItemInfo($oNewItemArgs);
		if($this->_g_oNewItemHeader->module_srl == svitem::S_NULL_SYMBOL || 
			$this->_g_oNewItemHeader->item_name == svitem::S_NULL_SYMBOL)
			return new BaseObject(-1,'msg_invalid_request');
		
		if($sMode == 'bulk') // excel bulk upload mode
			;
		return $this->_insertItem();
	}
/**
 * @brief 기존 품목 정보 적재
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
				$this->_initHeader();
                $oTmpArgs = new stdClass();
				$oTmpArgs->item_srl = $oParams->item_srl;
				$oTmpRst = executeQuery('svitem.getItemInfo', $oTmpArgs);
				if(!$oTmpRst->toBool())
					return $oTmpRst;
				if(count((array)$oTmpRst->data) == 0)
					return new BaseObject(-1,'msg_invalid_item_request');
				unset($oTmpArgs);
				break;
		}
		if($oTmpRst->data->enhanced_item_info)
			$oTmpRst->data->enhanced_item_info = unserialize($oTmpRst->data->enhanced_item_info);

		$this->_matchOldItemInfo($oTmpRst->data);
		$this->_consturctExtraVars(); // extra_var 설정
		$this->_setReviewCnt(); // 후기수 설정

		$oModuleModel = getModel('module');
		$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($this->_g_oOldItemHeader->module_srl);
		$this->_g_oOldItemHeader->mid = $oModuleInfo->mid;
		unset($oModuleModel);
		unset($oModuleInfo);

		return $oTmpRst;
	}
/**
 * @brief 기존 품목 상세 정보 적재
 **/
	public function loadDetail($oParams)
	{
		if($this->_g_oOldItemHeader->thumb_file_srl)
		{
			$oFileModel = &getModel('file');
			$oFile = $oFileModel->getFile($this->_g_oOldItemHeader->thumb_file_srl);
			if($oFile)
				$this->_g_oOldItemHeader->thumbnail_url = getFullUrl().$oFile->download_url;
			unset($oFile);
			unset($oFileModel);
		}

		if( $oParams->sUaType == 'mob' ) // mob description 
		{
			$this->_g_oOldItemHeader->document_srl = $this->_g_oOldItemHeader->mob_doc_srl;
			$this->_g_oOldItemHeader->sDescription = htmlentities($this->_g_oOldItemHeader->mob_description);
			$this->_g_oOldItemHeader->ua_type = 'mob';
		}
		elseif( $oParams->sUaType == 'pc' ) // pc description 
		{
			$this->_g_oOldItemHeader->document_srl = $this->_g_oOldItemHeader->pc_doc_srl;
			$this->_g_oOldItemHeader->sDescription = htmlentities($this->_g_oOldItemHeader->pc_description);
			$this->_g_oOldItemHeader->ua_type = 'pc';
		}
		else // for open graph
		{
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($this->_g_oOldItemHeader->document_srl);
			$this->_g_oOldItemHeader->sDescription = $oDocument->getContentText();
			unset($oDocument);
			unset($oDocumentModel);
			$this->_g_oOldItemHeader->ua_type = 'og';
		}

		return new BaseObject();
	}
/**
 * @brief 기존 품목 정보 변경
 **/
	public function update($oItemArgs)
	{
		if( !$this->_g_oOldItemHeader )
			return new BaseObject(-1,'msg_required_to_load_old_information_first');

		$this->_matchNewItemInfo($oItemArgs);
		if( $this->_g_oNewItemHeader->item_srl == -1 )
			return new BaseObject(-1,'msg_invalid_request');
		
		// 고정값은 외부 쿼리로 변경하지 않음
		$this->_g_oNewItemHeader->document_srl = $this->_g_oOldItemHeader->document_srl;
		$this->_g_oNewItemHeader->mob_doc_srl = $this->_g_oOldItemHeader->mob_doc_srl;
		$this->_g_oNewItemHeader->pc_doc_srl = $this->_g_oOldItemHeader->pc_doc_srl;
		$this->_g_oNewItemHeader->gallery_doc_srl = $this->_g_oOldItemHeader->gallery_doc_srl;
		if( $this->_g_oNewItemHeader->module_srl == svitem::S_NULL_SYMBOL )
			$this->_g_oNewItemHeader->module_srl = $this->_g_oOldItemHeader->module_srl;

		return $this->_updateItem();
	}
/**
 * @brief 구매 옵션 정보 추가
 **/
	public function setBuyingOptions()
	{
	}
/**
 * @brief 구매 옵션 정보 가져오기
 **/
	public function getBuyingOptions()
	{
	}
/**
 * @brief 기존 품목 정보 비활성화
 * module_srl을 0으로 바꿔서 상품 관리자에서 검색되지 않게함
 **/
	public function deactivate()
	{
		if( !$this->_g_oOldItemHeader )
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
		if( $this->_g_oOldItemHeader->item_srl == svitem::S_NULL_SYMBOL )
			return new BaseObject(-1,'msg_invalid_request');
		
		$oArgs->item_srl = $this->_g_oOldItemHeader->item_srl;
		$oArgs->module_srl = 0; // 소속 모듈을 0으로 설정하여 검색되지 않게 함
		$oRst = executeQuery('svitem.updateAdminItemDeactivated', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		return new BaseObject();
	}
/**
 * @brief 상품 영구 삭제; 코드 블록만 유지하고 이 메소드의 접근을 차단함
 **/
	public function remove()
	{
		if( !$this->_g_oOldItemHeader )
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
		if( $this->_g_oOldItemHeader->item_srl == svitem::S_NULL_SYMBOL )
			return new BaseObject(-1,'msg_invalid_request');

		// delete related file
		$oFileController = &getController('file');
		$oFileController->deleteFile($this->_g_oOldItemHeader->thumb_file_srl);
		$oFileController->deleteFile($this->_g_oOldItemHeader->gallery_doc_srl);
		$oFileController->deleteFile($this->_g_oOldItemHeader->mob_doc_srl);
		$oFileController->deleteFile($this->_g_oOldItemHeader->pc_doc_srl);
		unset($oFileController);
		
		// delete document
		$oDocumentController = &getController('document');
		$oDocumentController->deleteDocument($item_info->document_srl);
		unset($oDocumentController);

		// delete db record
		$oArgs->item_srl = $item_srl;
		$oRst = executeQuery('svitem.deleteItem', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		$oRst = executeQuery('svitem.deleteSvitemExtraVars', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		unset($oArgs);
		return new BaseObject();
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
 * @brief set skeleton svitem header
 * svitem.item_consumer.php::_setSkeletonHeader()과 통일성 유지
 **/
	private function _setSkeletonHeader()
	{
		$aBasicAttr = ['item_srl', 'module_srl', 'document_srl', 'description', // open graph / pc detail page / mob detail page
						'disp_module_srl', 'category_node_srl', 'thumb_file_srl', 'gallery_doc_srl', 'mob_doc_srl', 'mob_description', 
						'pc_doc_srl', 'pc_description', 'item_code', 'barcode', 'enhanced_item_info', 'item_name', 'price', 'taxfree',
						'display', 'sales_count', 'current_stock', 'safe_stock', 'sv_tags', 'list_order', 'updatetime', 'regdate'];
						// 'delivery_info', 'related_items' // 폐기 예정

		$aEnhancedAttr = ['ga_brand_name', 'ga_category_name', 'ga_variation_name', 'gallery_rep_thumbnail_idx', 'extmall_log_conf', 
							'item_brief', 'badge_icon', 'description_skin_pc', 'description_skin_mob'];

		$aInMemoryAttr = ['review_count', 'mid', 'sDescription', 'extra_vars' ]; // 'extra_vars'는 unserialize된 구조체 적재

		$aEpAttr = ['naver_ep_item_name', 'naver_ep_maker', 'naver_ep_origin', 'naver_ep_search_tag', 'naver_ep_barcode', 
						'naver_ep_naver_category', 'naver_ep_event_words', 'naver_ep_sv_campaign2', 'naver_ep_sv_campaign3', 'daum_ep_item_name'];
		
		$aTempAttr = ['ua_type', 'thumbnail_image'];

		foreach(self::A_ITEM_HEADER_TYPE as $nTypeIdx => $sHeaderType)
		{
            $this->{$sHeaderType} = new stdClass();
			foreach($aBasicAttr as $nAttrIdx => $sAttrName)
				$this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;

			// in-memory item info
			foreach($aInMemoryAttr as $nAttrIdx => $sAttrName)
				$this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
			
			// queried enhanced item info
			foreach($aEnhancedAttr as $nAttrIdx => $sAttrName)
				$this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
			
			// queried shopping ep
			foreach($aEpAttr as $nAttrIdx => $sAttrName)
				$this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
			
			// temp item info for insertion
			foreach($aTempAttr as $nAttrIdx => $sAttrName)
				$this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
		}

        // private $_g_oOldItemHeader = NULL; // 정보 수정할 때 과거 상태에 관한 참조일 뿐
	    // private $_g_oNewItemHeader 
		unset($aBasicAttr);
		unset($aEnhancedAttr);
		unset($aInMemoryAttr);
		unset($aEpAttr);
		unset($aTempAttr);
	}
/**
 * @brief 헤더에 extra_vars 설정
 * svitem.item_consumer.php::_consturctExtraVars()와 통일성 유지
 **/
	private function _consturctExtraVars($oParams = null)
	{
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
		$oArg = new stdClass();
		$oArg->nItemSrl = $this->_g_oOldItemHeader->item_srl;
		$oArg->nModuleSrl = $this->_g_oOldItemHeader->module_srl;
		$this->_g_oOldItemHeader->extra_vars = $oExtraVarsController->getExtendedVarsNameValueByItemSrl($oArg);
		foreach( self::A_ITEM_HEADER_TYPE as $nTypeIdx => $sHeaderType )
		{
			foreach( $aExtraVar as $nIdx => $oExtraVar )
			{
				$sAttrName = $oExtraVar->name;
				$this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
			}
		}
		unset($oArg);
		unset($oExtraVarsController);
	}
/**
 * @brief
 */
	private function _setReviewCnt()
	{
		$nReviewCnt = 0;
		if( $this->_g_oSvitemModuleConfig->connected_review_board_srl > 0 )
		{
			$oDocumentModel = getModel('document');
			$sCategoryContent = $oDocumentModel->getCategoryPhpFile($this->_g_oSvitemModuleConfig->connected_review_board_srl);
			unset($oDocumentModel);
			require($sCategoryContent);	
			foreach( $this->_g_oSvitemModuleConfig->review_for_item[$this->_g_oOldItemHeader->item_srl] as $key=>$val)
			{
				if( $val == 'match' )
					$nReviewCnt += (int)$menu->list[$key]['document_count'];
			}
		}
		$this->_g_oOldItemHeader->review_count = $nReviewCnt;
	}
/**
 * @brief 
 **/
	private function _matchOldItemInfo($oItemArgs)
	{
		$aIgnoreVar = array('module', 'mid', 'act', '__related_items', '__extra_vars' );
		foreach( $oItemArgs as $sTitle => $sVal)
		{
			if(in_array($sTitle, $aIgnoreVar)) 
				continue;

			if( $this->_g_oOldItemHeader->{$sTitle} == svitem::S_NULL_SYMBOL )
			{
				if( $sVal )
					$this->_g_oOldItemHeader->{$sTitle} = $sVal;
			}
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
					var_dump( '2weird: '.$sTitle.' => '. $sVal );
					echo '<BR>';
				}
//////////////// for debug only
			}
		}
	}
/**
 * @brief 
 **/
	private function _matchNewItemInfo($oNewItemArgs)
	{
		$aIgnoreVar = array('error_return_url', 'ruleset', 'module', 'mid', 'act' );
		$aCleanupVar = array('ga_brand_name', 'ga_category_name', 'ga_variation_name', 'naver_ep_item_name', 'naver_ep_maker', 'naver_ep_origin', 'naver_ep_search_tag', 'naver_ep_barcode', 'naver_ep_naver_category', 'naver_ep_event_words', 'naver_ep_sv_campaign2', 'naver_ep_sv_campaign3', 'daum_ep_item_name' );

		foreach($oNewItemArgs as $sTitle => $sVal)
		{
			if(in_array($sTitle, $aIgnoreVar)) 
				continue;

			if(in_array($sTitle, $aCleanupVar))
				$sVal = trim(strip_tags($sVal));
			
			if($sTitle == 'naver_ep_sv_campaign2' || $sTitle == 'naver_ep_sv_campaign3')
				$sVal = strtoupper($sVal);

			if($sTitle == 'item_brief' || $sTitle == 'description_skin_pc' || $sTitle == 'description_skin_mob')
				$sVal = trim( $sVal );

			if($sTitle == 'naver_ep_barcode' )
				$sVal = preg_replace("/[^0-9]*/s", '', $sVal); 
		
			if($this->_g_oNewItemHeader->$sTitle == svitem::S_NULL_SYMBOL)
				$this->_g_oNewItemHeader->$sTitle = $sVal;
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
					var_dump( '1weird: '.$sTitle.' => '. $sVal );
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
		foreach($this->_g_oNewItemHeader as $sTitle => $sVal)
		{
			if($sVal == svitem::S_NULL_SYMBOL)
				$this->_g_oNewItemHeader->$sTitle = null;
		}
	}
/**
 * @brief 헤더 초기화
 **/
	private function _initHeader()
	{
		foreach($this->_g_oNewItemHeader as $sTitle => $sVal)
			$this->_g_oNewItemHeader->$sTitle = svitem::S_NULL_SYMBOL;
		if(is_null($this->_g_oNewItemHeader))
            $this->_g_oNewItemHeader = new stdClass();
        foreach($this->_g_oOldItemHeader as $sTitle => $sVal)
			$this->_g_oOldItemHeader->$sTitle = svitem::S_NULL_SYMBOL;
        if(is_null($this->_g_oOldItemHeader))
            $this->_g_oOldItemHeader = new stdClass();
	}
/**
 * @brief 
 **/
	private function _insertItem()
	{
		// insert document for OG
		$this->_g_oNewItemHeader->document_srl = getNextSequence();
		$oDocArgs = new stdClass();
        $oDocArgs->document_srl = $this->_g_oNewItemHeader->document_srl;
		$oDocArgs->module_srl = $this->_g_oNewItemHeader->module_srl;
		$oDocArgs->content = $this->_g_oNewItemHeader->item_name.'의 상세페이지';
		$oDocArgs->title = $this->_g_oNewItemHeader->item_name;
		$oDocArgs->list_order = $this->_g_oNewItemHeader->document_srl * -1;
		$oDocArgs->tags = Context::get('tag');
		$oDocArgs->allow_comment = 'Y';
		$oDocumentController = &getController('document');
		$oDocRst = $oDocumentController->insertDocument($oDocArgs);
		if (!$oDocRst->toBool())
			return $oDocRst;
		unset($oDocRst);
		unset($oDocArgs);

		// set item_srl
		$this->_g_oNewItemHeader->item_srl = getNextSequence(); // doc srl과 통일할 수 있는지 검토
		
		// pre-treat unset value
		if($this->_g_oNewItemHeader->item_code == svitem::S_NULL_SYMBOL || $this->_g_oNewItemHeader->item_code == null)
			$this->_g_oNewItemHeader->item_code = $this->_g_oNewItemHeader->item_srl;

		$this->_nullifyHeader();
        $oArgs = new stdClass();
		$oArgs->item_srl = $this->_g_oNewItemHeader->item_srl;
		$oArgs->item_code = $this->_g_oNewItemHeader->item_code;
		$oArgs->barcode = $this->_g_oNewItemHeader->barcode;
		$oArgs->item_name = $this->_g_oNewItemHeader->item_name;
		$oArgs->module_srl = $this->_g_oNewItemHeader->module_srl;
		$oArgs->category_node_srl = 0;
		$oArgs->document_srl = $this->_g_oNewItemHeader->document_srl;
		$oArgs->mob_doc_srl = getNextSequence();
		$oArgs->pc_doc_srl = getNextSequence();
		$oArgs->gallery_doc_srl = getNextSequence();
		$oArgs->price = $this->_g_oNewItemHeader->price;
		$oArgs->taxfree = $this->_g_oNewItemHeader->taxfree;
		$oArgs->display = 'N';//$this->_g_oNewItemHeader->display; // 기본 최소 정보만 최초 등록하므로 무조건 비공개
		$oArgs->list_order = $this->_g_oNewItemHeader->item_srl * -1;
		
		// begin GA EEC info processing
		$oEnhancedItemInfo = new stdClass();
		$oEnhancedItemInfo->ga_brand_name = $this->_g_oNewItemHeader->ga_brand_name == svitem::S_NULL_SYMBOL ? null : $this->_g_oNewItemHeader->ga_brand_name;
		$oEnhancedItemInfo->ga_category_name = $this->_g_oNewItemHeader->ga_category_name == svitem::S_NULL_SYMBOL ? null : $this->_g_oNewItemHeader->ga_category_name;;
		$oEnhancedItemInfo->ga_variation_name = $this->_g_oNewItemHeader->ga_variation_name == svitem::S_NULL_SYMBOL ? null : $this->_g_oNewItemHeader->ga_variation_name;;
		$oArgs->enhanced_item_info = serialize($oEnhancedItemInfo);
		unset($oEnhancedItemInfo);
		// end GA EEC & SEO info processing
		
		// save representative thumbnail
		if($this->_g_oNewItemHeader->thumbnail_image['tmp_name']) 
		{
			$oFileController = &getController('file');
			if(is_uploaded_file($this->_g_oNewItemHeader->thumbnail_image['tmp_name'])) // single upload via web interface mode
			{
				$oFileRst = $oFileController->insertFile($this->_g_oNewItemHeader->thumbnail_image, $this->_g_oNewItemHeader->module_srl, $this->_g_oNewItemHeader->item_srl);
				if(!$oFileRst || !$oFileRst->toBool())
					return $oFileRst;
				$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
				$oArgs->thumb_file_srl = $oFileRst->get('file_srl');
				unset($oFileRst);
			}
			elseif( $this->_g_oNewItemHeader->thumbnail_image['size'] ) // excel bulk mode
			{
				echo 'yes img->'.$this->_g_oNewItemHeader->thumbnail_image['name'].'<BR>';
				$oFileRst = $oFileController->insertFile($this->_g_oNewItemHeader->thumbnail_image, $this->_g_oNewItemHeader->module_srl, $this->_g_oNewItemHeader->item_srl, 0, true);
				if(!$oFileRst || !$oFileRst->toBool())
					return $oFileRst;
				$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
				$oArgs->thumb_file_srl = $oFileRst->get('file_srl');
				unset($oFileRst);
				unset($oFileController);
			}
			else
			{
				echo 'no img->'.$oArgs->thumbnail_image['name'].'<BR>';
				$oArgs->thumb_file_srl = 0;
			}
			unset($oFileController);
		}
		$oInsertRst = executeQuery('svitem.insertItem', $oArgs);
		if(!$oInsertRst->toBool())
			return $oInsertRst;
		unset($oArgs);
		$oInsertRst->add('nItemSrl', $this->_g_oNewItemHeader->item_srl);
		return $oInsertRst;
	}
/**
 * @brief 
 **/
	private function _updateItem()
	{
		$this->_nullifyHeader();
		// 기본 정보 설정
        $oArgs = new stdClass();
		$oArgs->item_srl = $this->_g_oOldItemHeader->item_srl; // item_srl은 수정하면 안됨
		$oArgs->module_srl = $this->_g_oNewItemHeader->module_srl;

		if($this->_g_oNewItemHeader->item_name)
			$oArgs->item_name = $this->_g_oNewItemHeader->item_name;
		if($this->_g_oNewItemHeader->item_code)
			$oArgs->item_code = $this->_g_oNewItemHeader->item_code;
		if($this->_g_oNewItemHeader->barcode)
			$oArgs->barcode = $this->_g_oNewItemHeader->barcode;
		if($this->_g_oNewItemHeader->price)
			$oArgs->price = $this->_g_oNewItemHeader->price;
		if($this->_g_oNewItemHeader->current_stock)
			$oArgs->current_stock = $this->_g_oNewItemHeader->current_stock;
		if($this->_g_oNewItemHeader->safe_stock)
			$oArgs->safe_stock = $this->_g_oNewItemHeader->safe_stock;
		if($this->_g_oNewItemHeader->taxfree)
			$oArgs->taxfree = $this->_g_oNewItemHeader->taxfree;
		if($this->_g_oNewItemHeader->display)
			$oArgs->display = $this->_g_oNewItemHeader->display;
		if($this->_g_oNewItemHeader->list_order)
			$oArgs->list_order = $this->_g_oNewItemHeader->list_order;
		if($this->_g_oNewItemHeader->sv_tags)
			$oArgs->sv_tags = $this->_g_oNewItemHeader->sv_tags;

		$oEnhancedItemInfo = new stdClass();
		// begin GA EEC & EXTRA info processing
		$oEnhancedItemInfo->ga_brand_name = $this->_g_oNewItemHeader->ga_brand_name ? $this->_g_oNewItemHeader->ga_brand_name : $this->_g_oOldItemHeader->enhanced_item_info->ga_brand_name;
		$oEnhancedItemInfo->ga_category_name = $this->_g_oNewItemHeader->ga_category_name ? $this->_g_oNewItemHeader->ga_category_name : $this->_g_oOldItemHeader->enhanced_item_info->ga_category_name;
		$oEnhancedItemInfo->ga_variation_name = $this->_g_oNewItemHeader->ga_variation_name ? $this->_g_oNewItemHeader->ga_variation_name : $this->_g_oOldItemHeader->enhanced_item_info->ga_variation_name;
		
		if($this->_g_oNewItemHeader->gallery_rep_thumbnail_idx != svitem::S_NULL_SYMBOL)
		{
			$oFileModel = getModel('file');
			$nThumFileCnt = $oFileModel->getFilesCount($this->_g_oNewItemHeader->gallery_doc_srl);
			if($this->_g_oNewItemHeader->gallery_rep_thumbnail_idx > $nThumFileCnt - 1)
				return new BaseObject(-1, 'msg_invalid_gallery_rep_thumbnail_idx');
			$oEnhancedItemInfo->rep_gallery_thumb_idx = $this->_g_oNewItemHeader->gallery_rep_thumbnail_idx;
		}
		if($this->_g_oNewItemHeader->item_brief)
			$oEnhancedItemInfo->item_brief = $this->_g_oNewItemHeader->item_brief;
		
		// OG description,  mob PC 상세페이지 내용 설정
		switch($this->_g_oNewItemHeader->ua_type)
		{
			case 'og':
                $oDocArgs = new stdClass();
				$oDocArgs->document_srl = $this->_g_oNewItemHeader->document_srl;
				$oDocArgs->module_srl = $this->_g_oNewItemHeader->module_srl;
				$oDocArgs->content = $this->_g_oNewItemHeader->description;
				$oDocArgs->title = $this->_g_oNewItemHeader->item_name;
				$oDocArgs->list_order = $this->_g_oNewItemHeader->document_srl * -1;
				$oDocArgs->tags = Context::get('tag');
				$oDocArgs->allow_comment = 'Y';
				$oDocumentModel = &getModel('document');
				$oDocumentController = &getController('document');
				$oDocRst = $oDocumentController->updateDocument($oDocumentModel->getDocument($this->_g_oNewItemHeader->document_srl), $oDocArgs);
				if(!$oDocRst->toBool())
					return $oDocRst;

				unset($oDocRst);
				unset($oDocumentModel);
				unset($oDocumentController);
				unset($oDocArgs);
				break;
			case 'mob':
				if($this->_g_oNewItemHeader->description)
					$oArgs->mob_description = $this->_g_oNewItemHeader->description;
				break;
			case 'pc':
				if($this->_g_oNewItemHeader->description)
					$oArgs->pc_description = $this->_g_oNewItemHeader->description;
				break;
		}

		// naver EP info processing
		$oEnhancedItemInfo->naver_ep_item_name = $this->_g_oNewItemHeader->naver_ep_item_name ? $this->_g_oNewItemHeader->naver_ep_item_name : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_item_name;
		$oEnhancedItemInfo->naver_ep_maker = $this->_g_oNewItemHeader->naver_ep_maker ? $this->_g_oNewItemHeader->naver_ep_maker : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_maker;
		$oEnhancedItemInfo->naver_ep_origin = $this->_g_oNewItemHeader->naver_ep_origin ? $this->_g_oNewItemHeader->naver_ep_origin : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_origin;
		$oEnhancedItemInfo->naver_ep_search_tag = $this->_g_oNewItemHeader->naver_ep_search_tag ? $this->_g_oNewItemHeader->naver_ep_search_tag : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_search_tag;
		$oEnhancedItemInfo->naver_ep_barcode = $this->_g_oNewItemHeader->naver_ep_barcode ? $this->_g_oNewItemHeader->naver_ep_barcode : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_barcode;
		$oEnhancedItemInfo->naver_ep_naver_category = $this->_g_oNewItemHeader->naver_ep_naver_category ? $this->_g_oNewItemHeader->naver_ep_naver_category : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_naver_category;
		$oEnhancedItemInfo->naver_ep_event_words = $this->_g_oNewItemHeader->naver_ep_event_words ? $this->_g_oNewItemHeader->naver_ep_event_words : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_event_words;
		if($this->_g_oNewItemHeader->naver_ep_sv_campaign2)
		{
			if(!ctype_alnum( $this->_g_oNewItemHeader->naver_ep_sv_campaign2)) 
				return new BaseObject(-1, 'msg_invalid_naverep_sv_campaign_code');
			else
				$oEnhancedItemInfo->naver_ep_sv_campaign2 = $this->_g_oNewItemHeader->naver_ep_sv_campaign2 ? $this->_g_oNewItemHeader->naver_ep_sv_campaign2 : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_sv_campaign2;
		}
		if($this->_g_oNewItemHeader->naver_ep_sv_campaign3)
		{
			if(!ctype_alnum( $this->_g_oNewItemHeader->naver_ep_sv_campaign3)) 
				return new BaseObject(-1, 'msg_invalid_naverep_sv_campaign_code');
			else
				$oEnhancedItemInfo->naver_ep_sv_campaign3 = $this->_g_oNewItemHeader->naver_ep_sv_campaign3 ? $this->_g_oNewItemHeader->naver_ep_sv_campaign3 : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_sv_campaign3;
		}
		
		// daum EP info processing
		$oEnhancedItemInfo->daum_ep_item_name = $this->_g_oNewItemHeader->daum_ep_item_name ? $this->_g_oNewItemHeader->daum_ep_item_name : $this->_g_oOldItemHeader->enhanced_item_info->daum_ep_item_name;
		
		// badge info processing
		if($this->_g_oNewItemHeader->badge_icon)
		{
			$aBadgeIconToSave = [];
			foreach($this->_g_oNewItemHeader->badge_icon as $nIdx=>$sVal)
				$aBadgeIconToSave[$sVal] = 1;
			$oEnhancedItemInfo->default_badge_icon = $aBadgeIconToSave;
		}
		else
			$oEnhancedItemInfo->default_badge_icon = $this->_g_oOldItemHeader->enhanced_item_info->default_badge_icon;
		
		// 개발자용 PC / MOB 상세 페이지 등록
		$oEnhancedItemInfo->description_skin_pc = $this->_g_oNewItemHeader->description_skin_pc ? $this->_g_oNewItemHeader->description_skin_pc : $this->_g_oOldItemHeader->enhanced_item_info->description_skin_pc;
		if($oEnhancedItemInfo->description_skin_pc == 'use_each_pc')
			$oEnhancedItemInfo->description_skin_pc = null;
		$oEnhancedItemInfo->description_skin_mob = $this->_g_oNewItemHeader->description_skin_mob ? $this->_g_oNewItemHeader->description_skin_mob : $this->_g_oOldItemHeader->enhanced_item_info->description_skin_mob;
		if($oEnhancedItemInfo->description_skin_mob == 'use_each_mob')
			$oEnhancedItemInfo->description_skin_mob = null;
		// end GA EEC & EXTRA info processing

		$oArgs->enhanced_item_info = serialize($oEnhancedItemInfo);
		unset($oEnhancedItemInfo);
		$oUpdateRst = executeQuery('svitem.updateAdminItem', $oArgs);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		unset($oUpdateRst);

		// extra_vars update
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
		// begin - retrieve extended var info
        $oParam = new stdClass();
		$oParam->nModuleSrl = $this->_g_oNewItemHeader->module_srl;
		$oParam->nItemSrl = $this->_g_oNewItemHeader->item_srl;
		$oExtendedVarRst = $oExtraVarsController->getExtendedVarsNameValueByItemSrl($oParam);
		foreach($oExtendedVarRst->data as $nIdx => $oExtVar)
		{
			$sVarTitle = $oExtVar->column_name;
			if($this->_g_oNewItemHeader->{$sVarTitle})
				$oParam->oExtendedVar->{$sVarTitle} = $this->_g_oNewItemHeader->{$sVarTitle};
		}
		unset($oExtendedVarRst);
		// end - retrieve extended var info
		//$oParam->nModuleSrl = $this->_g_oNewItemHeader->module_srl;
		//$oParam->nItemSrl = $this->_g_oNewItemHeader->item_srl;
		$oUpdateRst = $oExtraVarsController->registerOnExtendedVar($oParam);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		unset($oParam);
		unset($oUpdateRst);
		unset($oExtraVarsController);
		
		// 첨부 이미지 파일 처리
		$oUpdateRst = $this->_procThumbnailImages();
		return $oUpdateRst;
	}
/**
 * @brief 첨부 이미지 파일 처리
 **/
	private function _procThumbnailImages()
	{
		$oFileController = getController('file');
		// 카탈로그 썸네일 파일 변경
		if(is_uploaded_file($this->_g_oNewItemHeader->thumbnail_image['tmp_name'])) 
		{
			// delete old catalog thumbnail
			if($this->_g_oOldItemHeader->thumb_file_srl) 
				$oFileController->deleteFile($this->_g_oOldItemHeader->thumb_file_srl);
			// attach new catalog thumbnail
			$oTmpRst = $oFileController->insertFile($this->_g_oNewItemHeader->thumbnail_image, $this->_g_oNewItemHeader->module_srl, $this->_g_oNewItemHeader->item_srl);
			if(!$oTmpRst || !$oTmpRst->toBool())
				return $oTmpRst;
			$this->_g_oNewItemHeader->thumb_file_srl = $oTmpRst->get('file_srl');
			unset($oTmpRst);
            $oTmpArgs = new stdClass();
			$oTmpArgs->item_srl = $this->_g_oNewItemHeader->item_srl;
			$oTmpArgs->thumb_file_srl = $this->_g_oNewItemHeader->thumb_file_srl;
			$oUpdateRst = executeQuery('svitem.updateItemFile', $oTmpArgs);
			if(!$oUpdateRst->toBool())
				return $oUpdateRst;
			unset($oUpdateRst);
			unset($oTmpArgs);
			$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
		}
		// 갤러리 썸네일 이미지를 첨부한 후 저장한 상황에 대응
		$oFileController->setFilesValid($this->_g_oNewItemHeader->gallery_doc_srl);
		unset($oFileController);
		return new BaseObject();
	}
/**
 * @brief 품목 정보 전송
 **/
/*	public function getHeader($sTarget)
	{
		if( $sTarget == 'old' )
			return $this->_g_oOldItemHeader;
		else
			return $this->_g_oNewItemHeader;
	}*/
}
/* End of file svitem.item_admin.php */
/* Location: ./modules/svitem/svitem.item_admin.php */