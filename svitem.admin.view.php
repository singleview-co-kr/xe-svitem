<?php
/**
 * @class  svitemAdminView
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemAdminView
 */ 
class svitemAdminView extends svitem
{
/**
 * @brief Contructor
 **/
	public function init() 
	{
		// module이 svshopmaster일때 관리자 레이아웃으로
		if(Context::get('module') == 'svshopmaster')
		{
			$sClassPath = _XE_PATH_ . 'modules/svshopmaster/svshopmaster.class.php';
			if(file_exists($sClassPath))
			{
				require_once($sClassPath);
				$oSvshopmaster = new svshopmaster;
				$oSvshopmaster->init($this);
			}
		}

		$logged_info = Context::get('logged_info');
		if ($logged_info->is_admin!='Y')
			return new BaseObject(-1, 'msg_login_required');

		// module_srl이 있으면 미리 체크하여 존재하는 모듈이면 module_info 세팅
		$module_srl = Context::get('module_srl');
		if( !$module_srl && $this->module_srl )
		{
			$module_srl = $this->module_srl;
			Context::set( 'module_srl', $module_srl );
		}
		$oModuleModel = &getModel('module');
		// module_srl이 넘어오면 해당 모듈의 정보를 미리 구해 놓음
		if( $module_srl ) 
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl( $module_srl );
			if( !$module_info )
			{
				Context::set('module_srl','');
				$this->act = 'list';
			}
			else
			{
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}
		if($module_info && !in_array($module_info->module, array('svitem')))
			return $this->stop("msg_invalid_request");

		//if(Context::get('module')=='svshopmaster')
		//{
		//	$this->setLayoutPath('');
		//	$this->setLayoutFile('common_layout');
		//}
		// set template file
		$tpl_path = $this->module_path.'tpl';
		$this->setTemplatePath($tpl_path);
		$this->setTemplateFile('index');
		Context::set('tpl_path', $tpl_path);
	}
/**
 * @brief admin view for item list
 */
	public function dispSvitemAdminItemListByModule() 
	{
		$nModuleSrl = (int)Context::get('module_srl');
		if(!$nModuleSrl)
			return new BaseObject(-1, 'msg_invalid_request');

		$list_count = Context::get('disp_numb');
		$sort_index = Context::get('sort_index');
		$order_type = Context::get('order_type');
		if(!$list_count) 
			$list_count = 30;
		if(!$sort_index) 
			$sort_index = "list_order";
		if(!$order_type) 
			$order_type = 'asc';
		
		$sSearchItemName = Context::get('search_item_name');
		if(strlen($sSearchItemName))
			$args->item_name = $sSearchItemName;

        $args = new stdClass();
		$args->module_srl = $nModuleSrl;
		$args->page = Context::get('page');
		$args->list_count = $list_count;
		$args->sort_index = $sort_index;
		$args->order_type = $order_type;
		
		$oSvitemAdminModel = &getAdminModel('svitem');
		$output = $oSvitemAdminModel->getSvitemAdminItemList($args);
		if(!$output->toBool())
			return $output;
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('list', $output->data);
		// showwindow display
		
		$aShowWindowCategories = $oSvitemAdminModel->getSvitemAdminShowWindowCategoryByModuleSrl($nModuleSrl);
		Context::set('showwindow_categories', $aShowWindowCategories);
		$this->setTemplateFile('itemlist');
	}
/**
 * @brief 
 */
	public function dispSvitemAdminInsertItem() 
	{
		// 스킨은 $this->init()와 index.html에서 처리
	}
/**
 * @brief 
 */
	public function dispSvitemAdminUpdateItem() 
	{
		$oSvitemModel = &getModel('svitem');
		$oConfig = $oSvitemModel->getModuleConfig();
		Context::set('config',$oConfig);
		$nItemSrl = Context::get('item_srl');
		$sUaType = Context::get('ua_type');

		require_once(_XE_PATH_.'modules/svitem/svitem.item_admin.php');
		$oItemAdmin = new svitemItemAdmin();
        $oParams = new stdClass();
		$oParams->item_srl = $nItemSrl;
		$oTmpRst = $oItemAdmin->loadHeader($oParams);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_item_request');
		unset($oParams);
		unset($oTmpRst);
        $oParams = new stdClass();
		$oParams->sUaType = $sUaType;
		$oTmpRst = $oItemAdmin->loadDetail($oParams);
		if(!$oTmpRst->toBool())
			return $oTmpRst;
		unset($oParams);
		unset($oTmpRst);

		Context::set('item_info', $oItemAdmin);

		// editor
		$oEditorModel = &getModel('editor');
		Context::set('editor', $oEditorModel->getModuleEditor('document', $oItemAdmin->nModuleSrl, $oItemAdmin->document_srl, 'document_srl', 'description'));

		// gallery image editor
        $oOption = new stdClass();
		//$oOption->disable_html = true;
		//$oOption->enable_default_component = true;
		//$oOption->enable_component = true;
		//$oOption->module_type = 'document';
		//$oOption->skin = 'ckeditor';
		//$oOption->content_style = 'ckeditor_light';
		$oOption->content_key_name = 'null';
		$oOption->height = 1;
		$oOption->allow_fileupload = true;
		$oOption->primary_key_name = 'document_srl';
		Context::set('gallery_editor', $oEditorModel->getEditor( $oItemAdmin->gallery_doc_srl, $oOption)); // 갤러리 첨부파일 로드하기 위한 hidden 객체
		unset($oOption);
		unset($oEditorModel);

		// get options
        $oOptionArgs = new stdClass();
		$oOptionArgs->item_srl = $nItemSrl;
		$oOptionRst = executeQueryArray('svitem.getOptions', $oOptionArgs);
		if(!$oOptionRst->toBool())
			return $oOptionRst;
		Context::set('options', $oOptionRst->data);
		unset($oOptionArgs);
		unset($oOptionRst);
		
		// 시작 - 추가등록폼 관리에서 추가한 변수 호출
		Context::set('extra_vars', $oItemAdmin->extra_vars );
		
		// 시작 - 진열페이지 목록
		$oSvitemAdminModel = &getAdminModel('svitem');
		$aSvitemMid = $oSvitemAdminModel->getModInstList();
		Context::set('modinst', $aSvitemMid);
		unset($oSvitemAdminModel);
		unset($aSvitemMid);
		// 종료 - 진열페이지 목록

		$sDescriptionSkinPath = _XE_PATH_.'files/svitem';
		FileHandler::makeDir($sDescriptionSkinPath);
		$aSkinFiles = FileHandler::readDir($sDescriptionSkinPath );
		$aDisplaySkinFiles = [];
		foreach($aSkinFiles as $key=>$val)
		{
			if(strpos($val, '.html') && strpos($val, 'html.') === FALSE)
				$aDisplaySkinFiles[] = $val;
		}
		Context::set('description_skin_list', $aDisplaySkinFiles);
		unset($aSkinFiles);
		unset($aDisplaySkinFiles);
		unset($oItemAdmin);
	}
/**
 * @brief admin view [바코드 설정]
 */
	public function dispSvitemAdminBarcodeMgmt() 
	{
		$oSvitemModel = getModel('svitem');
		$oModuleConfig = $oSvitemModel->getModuleConfig();
		$nBarcodeSrl = Context::get('barcode_srl');
//$oArgs = Context::getRequestVars();
//var_dump( $oModuleConfig->aBarcodeDdlGroup);
//		if(!$nBarcodeSrl)
//			$aChoosedGroup = array_shift($oModuleConfig->aBarcodeDdlGroup);
//		elseif($nBarcodeSrl=='new')
//			$aChoosedGroup = null;
//		else
//			$aChoosedGroup = $oModuleConfig->aBarcodeDdlGroup[$nBarcodeSrl];

//echo '<BR><BR>';
		Context::set('barcode_ddl_group', $oModuleConfig->aBarcodeDdlGroup);
	}
/**
 * @brief 
 */
	public function dispSvitemAdminExtMallLogger() 
	{
		$item_srl = (int)Context::get('item_srl');
		if(!$item_srl)
			return new BaseObject(-1, 'msg_invalid_request');
		
		$oSvitemModel = &getModel('svitem');
		$item_info = $oSvitemModel->getItemInfoByItemSrl($item_srl);
		Context::set('item_name', $item_info->item_name);
		$oExtraVars = unserialize( $item_info->extmall_log_conf );
		Context::set('item_extmall_logger_config', $oExtraVars->toggle);
		
		$getAdminModel = &getAdminModel('svitem');
		$aExtMallList = $getAdminModel->getExtMallListByItemSrl($item_srl);
		Context::set('extmall_list', $aExtMallList);

		$this->setTemplateFile('extmall_logger');
	}
/**
 * @brief default admin view
 */
	public function dispSvitemAdminModInstList() 
	{
		$oModuleModel = &getModel('module');
		$args = new stdClass();
		$args->sort_index = "module_srl";
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');
		$output = executeQueryArray('svitem.getModInstList', $args);
		$list = $output->data;
		$list = $oModuleModel->addModuleExtraVars($list);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('list', $list);
		$oModuleModel = &getModel('module');
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
	}
/**
 * @brief admin view [기본설정]
 */
	public function dispSvitemAdminConfig() 
	{
		$oSvitemAdminModel = &getAdminModel('svitem');
		$config = $oSvitemAdminModel->getModuleConfig();
		$aNvrEpSvitemModules = array();
		foreach( $config->naver_ep_extract_svitem as $k=>$v)
			$aNvrEpSvitemModules[$v] = 'extract';
		$config->naver_ep_extract_svitem = $aNvrEpSvitemModules;

		$aDaumEpSvitemModules = array();
		foreach( $config->daum_ep_extract_svitem as $k=>$v)
			$aDaumEpSvitemModules[$v] = 'extract';
		$config->daum_ep_extract_svitem = $aDaumEpSvitemModules;

		Context::set('config',$config);	
		$oSvitemModules = array();
		$oModuleModel = &getModel('module');
		$oModules = $oModuleModel->getMidList();
		foreach($oModules as $key=>$val)
		{
			if($val->module == 'svitem')
			{
                if(is_null($oSvitemModules[$nIdx]))
                    $oSvitemModules[$nIdx] = new stdClass();
				$oSvitemModules[$nIdx]->module_srl = $val->module_srl;
				$oSvitemModules[$nIdx++]->mid = $val->mid;
			}
		}
		Context::set('svitem_mod_list',$oSvitemModules);
		$this->setTemplateFile('config');
	}
/**
 * @brief 
 */
	public function dispSvitemAdminInsertModInst() 
	{
		// 스킨 목록을 구해옴
		$oModuleModel = &getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		// 레이아웃 목록을 구해옴
		$oLayoutModel = &getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		$oSvcartModel = &getModel('svcart');
		if($oSvcartModel)
		{
			$svcart_insts = $oSvcartModel->getModInstList();
			Context::set('svcart_insts', $svcart_insts);
		}
		$oEditorModel = &getModel('editor');
		$config = $oEditorModel->getEditorConfig(0);
		// 에디터 옵션 변수를 미리 설정
		$option = new stdClass();
		$option->skin = $config->editor_skin;
		$option->content_style = $config->content_style;
		$option->content_font = $config->content_font;
		$option->content_font_size = $config->content_font_size;
		$option->colorset = $config->sel_editor_colorset;
		$option->allow_fileupload = true;
		$option->enable_default_component = true;
		$option->enable_component = true;
		$option->disable_html = false;
		$option->height = 200;
		$option->enable_autosave = false;
		$option->primary_key_name = 'module_srl';
		$option->content_key_name = 'delivery_info';
		$editor = $oEditorModel->getEditor($this->module_info->module_srl, $option);
		Context::set('editor', $editor);
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
	}
/**
 * @brief display the QNA board connection management
 **/
	public function dispSvitemAdminQnaBoardMgmt()
	{
		$oSvitemAdminModel = &getAdminModel('svitem');
		$config = $oSvitemAdminModel->getModuleConfig();
		$output = executeQueryArray('board.getBoardList', $args);
		ModuleModel::syncModuleToSite($output->data);
		
		$nIdx = 0;
		$aBoard = array();
		foreach( $output->data as $key=>$val)
		{
			if(is_null($aBoard[$nIdx]))
				$aBoard[$nIdx] = new stdClass();
			$aBoard[$nIdx]->module_srl =  $val->module_srl;
			$aBoard[$nIdx++]->mid =  $val->mid;
		}
		
		if( $config->connected_qna_board_srl )
		{
			$sLangSelected = Context::getLangType();
			$oDocumentModel = getModel('document');
			$category_content = $oDocumentModel->getCategoryPhpFile($config->connected_qna_board_srl);
			require_once($category_content );
			$aQnaCategory = array();
			foreach($_titles as $key=>$val)
				$aQnaCategory[$key] = $val[$sLangSelected];

			Context::set('qna_category',$aQnaCategory);
			$oSvitemAdminModel = &getAdminModel('svitem');
			$aDisplayList = $oSvitemAdminModel->getAllDisplayingItemList();
			$aDisplayItems = array();
			foreach( $aDisplayList as $key=>$val)
			{
				$aDisplayItems[$val->item_srl]->item_name = $val->item_name;
				$aDisplayItems[$val->item_srl]->mid = $val->mid;
			}
			Context::set('display_items',$aDisplayItems);
			// install order (sorting) options
			$aOrderTarget = array('list_order', 'update_order', 'regdate', 'voted_count', 'blamed_count', 'readed_count', 'comment_count', 'title', 'nick_name');
			foreach($aOrderTarget as $key) $order_target[$key] = Context::getLang($key);
			$order_target['list_order'] = Context::getLang('document_srl');
			$order_target['update_order'] = Context::getLang('last_update');
			Context::set('order_target', $order_target);
		}
		Context::set('config',$config);
		Context::set('board_list', $aBoard);
		$this->setTemplateFile('qnaboard_mgmt');
	}
/**
 * @brief display the review board connection management
 **/
	public function dispSvitemAdminReviewBoardMgmt()
	{
		$oSvitemAdminModel = &getAdminModel('svitem');
		$config = $oSvitemAdminModel->getModuleConfig();
		$output = executeQueryArray('board.getBoardList', $args);
		ModuleModel::syncModuleToSite($output->data);
		
		$nIdx = 0;
		$aBoard = array();
		foreach( $output->data as $key=>$val)
		{
			if(is_null($aBoard[$nIdx]))
				$aBoard[$nIdx] = new stdClass();
			$aBoard[$nIdx]->module_srl =  $val->module_srl;
			$aBoard[$nIdx++]->mid =  $val->mid;
		}
		
		if( $config->connected_review_board_srl )
		{
			$sLangSelected = Context::getLangType();
			$oDocumentModel = getModel('document');
			$category_content = $oDocumentModel->getCategoryPhpFile($config->connected_review_board_srl);
			require_once($category_content );
			$aReviewCategory = array();
			foreach($_titles as $key=>$val)
				$aReviewCategory[$key] = $val[$sLangSelected];

			Context::set('review_category',$aReviewCategory);
			$oSvitemAdminModel = &getAdminModel('svitem');
			$aDisplayList = $oSvitemAdminModel->getAllDisplayingItemList();
			$aDisplayItems = array();
			foreach( $aDisplayList as $key=>$val)
			{
				$aDisplayItems[$val->item_srl]->item_name = $val->item_name;
				$aDisplayItems[$val->item_srl]->mid = $val->mid;
			}

			Context::set('display_items',$aDisplayItems);
			// install order (sorting) options
			$aOrderTarget = array('list_order', 'update_order', 'regdate', 'voted_count', 'blamed_count', 'readed_count', 'comment_count', 'title', 'nick_name');
			foreach($aOrderTarget as $key) $order_target[$key] = Context::getLang($key);
			$order_target['list_order'] = Context::getLang('document_srl');
			$order_target['update_order'] = Context::getLang('last_update');
			Context::set('order_target', $order_target);
		}
		Context::set('config',$config);
		Context::set('board_list', $aBoard);
		$this->setTemplateFile('reviewboard_mgmt');
	}
/**
 * @brief 
 */
	public function dispSvitemAdminAdditionSetup() 
	{
		// content는 다른 모듈에서 call by reference로 받아오기에 미리 변수 선언만 해 놓음
		$content = '';
		// get the addtional setup trigger
		// the additional setup triggers can be used in many modules
		$output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'before', $content);
		$output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'after', $content);
		//$oEditorView = &getView('editor');
		//$oEditorView->triggerDispEditorAdditionSetup($content);
		Context::set('setup_content', $content);
	}
/**
 * @brief 
 */
	public function dispSvitemAdminShowWindowCatalog() 
	{
        $args = new stdClass();
		$args->module_srl = Context::get('module_srl');
		$output = executeQueryArray('svitem.getShowWindowCatalogList', $args);
		if(!$output->toBool()) 
			return $output;
		Context::set('list', $output->data);
		$this->setTemplateFile('show_window_catalog');
	}

/**
 * @brief 스킨 정보 보여줌
 **/
	public function dispSvitemAdminSkinInfo() 
	{
		// 공통 모듈 권한 설정 페이지 호출
		$oModuleAdminModel = &getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skininfo');
	}
/**
 * @brief 스킨 정보 보여줌
 **/
	public function dispSvitemAdminMobileSkinInfo() 
	{
		// 공통 모듈 권한 설정 페이지 호출
		$oModuleAdminModel = &getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skininfo');
	}
/**
 * @brief 
 */
	public function dispSvitemAdminBulkItems() 
	{
		$oSvitemModel = &getModel('svitem');
		$item_list = Context::get('item_list');
		$lines = explode("\n", $item_list);
		Context::set('item_list','');
		$update_list = array();
		$original_list = array();
		foreach ($lines as $line) 
		{
			$line = trim($line);
			$columns = explode("\t", $line);
			if(count($columns) != 5)
				continue;

			$obj = new StdClass();
			$obj->item_code = $columns[0];
			$obj->item_name = $columns[1];
			$obj->taxfree = ($columns[2] == '비과세') ? 'Y' : 'N';
			$obj->display = ($columns[3] == '진열함') ? 'Y' : 'N';
			$obj->price = $columns[4];
			$update_list[$obj->item_code] = $obj;
			$item_info = $oSvitemModel->getItemByCode($obj->item_code);
			$original_list[$obj->item_code] = $item_info;
		}
		Context::set('original_list', $original_list);
		Context::set('update_list', $update_list);
	}
/**
 * @brief 
 */
	public function dispSvitemAdminExtendVarSetup() 
	{
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
		$nModuleSrl = Context::get('module_srl');
		$oRst = $oExtraVarsController->getExtendVarsByModuleSrl($nModuleSrl);
		if(!$oRst->toBool()) 
			return $oRst;

		Context::set('list', $oRst->get('aFinalExtraVar'));
		unset($aRst);
		unset($oExtraVarsController);
	}
/**
 * @brief 
 */
	public function dispSvitemAdminListDisplaySetup()
	{
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
        $oArgs = new stdClass();
		$oArgs->sPageType = 'catalog';
		$oArgs->nModuleSrl = $this->module_info->module_srl;
		$oRst = $oExtraVarsController->getExtraVarsConfiguration($oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		Context::set('aDisplayingVars', $oRst->get('aDisplayingVars'));
		Context::set('aHiddenVars', $oRst->get('aHiddenVars') );
		unset($oRst); 
		unset($oExtraVarsController);
		unset($oArgs);

		$oSvitemModel = &getModel('svitem');
		$oConfig = $oSvitemModel->getMidLevelConfig($this->module_info->module_srl);
		Context::set('config', $oConfig);
		
		$security = new Security();
		$security->encodeHTML('list_config');
		$oSvitemAdminModel = &getAdminModel('svitem');
		$sExtScript = $oSvitemAdminModel->getExtScript($this->module_info->module_srl, 'list_page');
		Context::set('ext_script', htmlspecialchars($sExtScript) );
		$this->setTemplateFile('disp_list_setup');
	}
/**
 * @brief 
 */
	public function dispSvitemAdminDetailDisplaySetup()
	{
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
        $oArgs = new stdClass();
		$oArgs->sPageType = 'detail';
		$oArgs->nModuleSrl = $this->module_info->module_srl;
		$oRst = $oExtraVarsController->getExtraVarsConfiguration($oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		Context::set('aDisplayingVars', $oRst->get('aDisplayingVars'));
		Context::set('aHiddenVars', $oRst->get('aHiddenVars') );
		unset($oRst); 
		unset($oExtraVarsController);
		unset($oArgs);

		$oSvitemModel = &getModel('svitem');
		$oConfig = $oSvitemModel->getMidLevelConfig($this->module_info->module_srl);
		Context::set('config', $oConfig);

		$security = new Security();
		$security->encodeHTML('detail_list_config');
		$oSvitemAdminModel = &getAdminModel('svitem');
		$sExtScript = $oSvitemAdminModel->getExtScript($this->module_info->module_srl, 'detail_page');
		Context::set('ext_script', htmlspecialchars($sExtScript) );
		$this->setTemplateFile('disp_detail_setup');
	}
/**
 * @brief display the grant information
 **/
	public function dispSvitemAdminGrantInfo() 
	{
		// get the grant infotmation from admin module
		$oModuleAdminModel = &getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);
		$this->setTemplateFile('grantinfo');
	}
/**
 * @brief 
 */
	public function dispSvitemAdminItemListExcelDownload() 
	{
		$oSvitemModel = &getModel('svitem');
		$oSvitemView = &getView('svitem');
		$oSvitemView->getCategoryTree($this->module_info->module_srl);
		$category = Context::get('category');
		$list_count = Context::get('disp_numb');
		$sort_index = Context::get('sort_index');
		$order_type = Context::get('order_type');

		if(!$list_count) 
			$list_count = 30;
		if(!$sort_index) 
			$sort_index = "item_srl";
		if(!$order_type) 
			$order_type = 'asc';
		if($category) 
		{
			$category_info = $oSvitemModel->getCategoryInfo($category);
			$args->module_srl = Context::get('module_srl');
			$args->node_route = $category_info->node_route . $category_info->node_id . '.';
			$args->page = Context::get('page');
			$args->list_count = $list_count;
			$args->sort_index = $sort_index;
			$args->order_type = $order_type;
			$output = executeQueryArray('svitem.getItemsByNodeRoute', $args);
			if(!$output->toBool()) 
				return $output;
			$item_list = $output->data;
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);
		} 
		else 
		{
			$args->module_srl = Context::get('module_srl');
			$args->page = Context::get('page');
			$args->list_count = $list_count;
			$args->sort_index = $sort_index;
			$args->order_type = $order_type;
			$output = executeQueryArray('svitem.getItemsByNodeRoute', $args);
			if(!$output->toBool()) 
				return $output;
			$item_list = $output->data;
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);
		}
		// $oSvitemAdminModel->convertDataIntoSvitem($item_list);
		//if($item_list) 
		//{
		//	foreach ($item_list as $key=>$val) 
		//		$item_list[$key] = new svitemItem($val);
		//}
		Context::set('list', $item_list);
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('itemlist_exceldown');
		header("Content-Type: Application/octet-stream;");
		header("Content-Disposition: attachment; filename=\"ITEMLIST-" . date('Ymd') . ".xls\"");
	}
}
/* End of file svitem.admin.view.php */
/* Location: ./modules/svitem/svitem.admin.view.php */