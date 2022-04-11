<?php
/**
 * @class  svitemAdminModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemAdminModel
 */ 
class svitemAdminModel extends svitem
{
/**
 * @brief Contructor
 **/
	public function init() 
	{
		$logged_info = Context::get('logged_info');
		if ($logged_info->is_admin!='Y')
			return new BaseObject(-1, 'msg_login_required');
	}
/**
 * @brief 
 **/
	public function getModInstList() 
	{
		// get module instance list
		$args = new stdClass();
		$args->list_count = 1000;
		$output = executeQueryArray('svitem.getModInstList', $args);
		return $output->data;
	}
/**
 * @brief 
 **/
	public function getExtMallListByItemSrl($nItemSrl) 
	{
		if(!$nItemSrl)
			return Array();
		$args->item_srl = $nItemSrl;
		$args->is_active = 'Y';
		$output = executeQueryArray('svitem.getExtmallListByItemSrl', $args);
		return $output->data;
	}
/**
 * @brief 
 **/
	public function getExtScript($nModuleSrl, $sPageType)
	{
		if( !(int)$nModuleSrl )
			return 'invalid_module_srl';
		switch($sPageType)
		{
			case 'list_page':
			case 'detail_page':
				break;
			default:
				return null;
		}
		$sExtScriptFile = _XE_PATH_.'files/svitem/ext_script_'.$sPageType.'_'.$nModuleSrl.'.html';
		if(is_readable($sExtScriptFile))
			return FileHandler::readFile($sExtScriptFile);

		return null;
	}
/**
 * @brief get module level config
 * @return 
 **/
	public function getModuleConfig()
	{
		$oSvitemModel = &getModel('svitem');
		return $oSvitemModel->getModuleConfig();
	}
/**
 * @brief 모든 등록 상품의 카테고리 읽어오기
 * svitem.admin.controller.php::procSvorderAdminCSVDownloadByOrderAll()에서 호출
 */
	public function getAllItemCategoryInfo() 
	{
		$output = executeQueryArray('svitem.getItemList', $args);
		if (!$output->toBool())
			return;
		
		$aItemCategoryInfo = array();
		$nMaxDepth = 0;
		$oSvitemModel = &getModel('svitem');
		foreach( $output->data as $key=>$val )
		{
			if( $val->category_node_srl)
			{
				$nCurrentDepth = 0;
				$aTempCategory = array();
				$oCategoryInfo = $oSvitemModel->getCatalog( $val->module_srl, $val->category_node_srl);
				foreach( $oCategoryInfo->parent_catalog_list as $nCategorySrl => $oCategoryVal )
				{
					if($oCategoryVal->node_srl > 0)
					{
						$aTempCategory[] = $oCategoryVal->node_name;
						$nCurrentDepth++; 
					}
				}
				$aTempCategory[] = $oCategoryInfo->current_catalog_info->node_name;
				$nCurrentDepth++;
				$aItemCategoryInfo[$val->item_srl]->category_info = $aTempCategory;
				if( $nMaxDepth < $nCurrentDepth )
					$nMaxDepth = $nCurrentDepth;
			}
		}
		$aItemCategoryInfo['$_max_depth_$'] = $nMaxDepth;
		return $aItemCategoryInfo;
	}
/**
 * @brief 판매중인 상품 모두 읽어오기, 
 * svitem.admin.view.php::dispSvitemAdminReviewBoardMgmt()에서 호출
 * svitem.admin.view.php::dispSvitemAdminQnaBoardMgmt()에서 호출
 */
	public function getAllDisplayingItemList() 
	{
		$args = new stdClass();
		$args->display = 'Y';
		$output = executeQueryArray('svitem.getItemList', $args);
		if (!$output->toBool())
			return;
		
		$oModuleModel = getModel('module');
		foreach( $output->data as $key=>$val )
		{
			$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($val->module_srl);
			$output->data[$key]->mid = $oModuleInfo->mid;
		}
		return $output->data;
	}
/**
 * @brief extract information about all catalog nodes
 **/
	public function getCatalog($nModuleSrl, $nCatalogSrl)
	{
		if( !$nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_request');

		$args->module_srl = $nModuleSrl;
		$output = executeQueryArray('svitem.getCatalogNodeList', $args);
		if(count($output->data)==0)
			return;
		foreach( $output->data as $key=>$val)
		{
			$oItemArgs->module_srl = $nModuleSrl;
			$oItemArgs->category_node_srl = $val->category_node_srl;
			$oItemListByCategorySrl = executeQueryArray('svitem.getAdminItemListByCategorySrl', $oItemArgs);
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
		return $oRst;
	}
/**
 * @brief [상품관리] 메뉴에 jstree 1.3.3 json 데이터 출력
 * https://www.jstree.com/
 * http://phpflow.com/php/dynamic-tree-with-jstree-php-and-mysql/
 **/
	public function getSvitemAdminCatalogAjax() 
	{
		$nModuleSrl = Context::get('module_srl');
		$nNodeSrl = Context::get('category_node_srl');
		$aCategoryNode = array();
		if($nNodeSrl == 0) // simply get the root
		{
			$oRoot = new StdClass();
			//$obj->state = 'closed';
			$oRoot->id = 0; 
			$oRoot->text = Context::getLang('default_item_catalog');
			$oRoot->type = 'default';
			$oRoot->children = $this->_getCategoryChildrenList($nModuleSrl,$nNodeSrl);
			$aCategoryNode[] = $oRoot;
		}
		else // get node_route
			$aCategoryNode = $this->_getCategoryChildrenList($nModuleSrl,$nNodeSrl);

		Context::setResponseMethod('JSON');
		echo json_encode($aCategoryNode); // model class에서는 output 강제 출력해야 함
		$this->add('data', $aCategoryNode);
	}
/**
 * @brief 
 **/
	public function getSvitemAdminInsertItemExtra() 
	{
		$extra_srl = Context::get('extra_srl');
		$args = new stdClass();
        $args->extra_srl = $extra_srl;
		$output = executeQuery('svitem.getItemExtra', $args);

		if($output->toBool() && $output->data)
		{
			$formInfo = $output->data;
			$default_value = $formInfo->default_value;
			if($default_value)
			{
				$default_value = unserialize($default_value);
				Context::set('default_value', $default_value);
			}
			Context::set('formInfo', $output->data);
		}

		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_insert_item_extra');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief 
 **/
	public function getSvitemAdminInsertDeliveryInfo() 
	{
		$item_srl = Context::get('item_srl');
		$args->item_srl = $item_srl;
		$output = executeQuery('svitem.getItemInfo', $args);

		if($output->toBool() && $output->data)
		{
			$formInfo = $output->data;
			$default_value = $formInfo->default_value;
			if($default_value)
			{
				$default_value = unserialize($default_value);
				Context::set('default_value', $default_value);
			}
			Context::set('formInfo', $output->data);
		}
		$oEditorModel = &getModel('editor');
		$config = $oEditorModel->getEditorConfig(0);
		// 에디터 옵션 변수를 미리 설정
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
		$option->primary_key_name = 'item_srl';
		$option->content_key_name = 'delivery_info';
		$editor = $oEditorModel->getEditor(0, $option);
		Context::set('editor', $editor);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_insert_delivery_info');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief [제품목록] 화면 쇼윈도우 진열 버튼 관련
 * @return 
 **/
	public function getSvitemAdminShowWindowItemListAjax() 
	{
		$nModuleSrl = Context::get('module_srl');
		$nCategoryNodeSrl = Context::get('show_window_tab_srl');

		if( !$nModuleSrl || !strlen($nCategoryNodeSrl) )
			return new BaseObject(-1, 'msg_invalid_request');
        $args = new stdClass();
		$args->module_srl = $nModuleSrl;
		$args->category_srl = $nCategoryNodeSrl;
		$output = executeQueryArray('svitem.getAdminDisplayItemList', $args);
		if(!$output->toBool())
			return $output;
		if(count($output->data))
		{
			$oSvitemModel = &getModel('svitem');
			foreach($output->data as $key=>$val)
			{
				$oItem = $oSvitemModel->getItemInfoByItemSrl($val->item_srl);
				$output->data[$key]->item_name = $oItem->item_name;
			}
		}
		$this->add('data', $output->data);
	}
/**
 * @brief [제품목록] [메인진열 카테고리] 화면 관련
 **/
	public function getSvitemAdminShowWindowCategoryByModuleSrl($nModuleSrl) 
	{
		if(!$nModuleSrl)
			return new BaseObject(-1, 'msg_invalid_request');
        $args = new stdClass();
		$args->module_srl = $nModuleSrl;
		$output = executeQueryArray('svitem.getAdminShowWindowCategoryByModuleSrl', $args);
		if(!$output->toBool())
			return $output;
		return $output->data;
	}
/**
 * @brief 
 **/
	public function getSvitemAdminDeleteModInst() 
	{
		$oModuleModel = &getModel('module');
		$module_srl = Context::get('module_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('module_info', $module_info);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_delete_modinst');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief 
 **/
	public function getSvitemAdminDeleteItem() 
	{
		$oSvitemModel = &getModel('svitem');
		$item_srl = Context::get('item_srl');
		$item_info = $oSvitemModel->getItemInfoByItemSrl($item_srl);
		Context::set('item_info', $item_info);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_delete_item');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief 
 **/
	public function getSvitemAdminInsertOptions() 
	{
		$oSvitemModel = &getModel('svitem');
		$item_srl = Context::get('item_srl');
		$options = $oSvitemModel->getOptions($item_srl);
		Context::set('options', $options);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_insert_options');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief 
 **/
	public function getSvitemAdminInsertBundling() 
	{
		$oSvitemModel = &getModel('svitem');
		$item_srl = Context::get('item_srl');
		$bundlings = $oSvitemModel->getBundlings( $item_srl );
		Context::set( 'bundlings', $bundlings );
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_insert_bundling');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief procSvitemAdminUpdateItemDefaultCatalogInfo에서 호출
 **/
	public function getSvitemAdminItemInfo($nModuleSrl,$nItemSrl) 
	{
		if(!$nModuleSrl || !$nItemSrl)
			return new BaseObject(-1, 'msg_invalid_request');

		$oArg->module_srl = $nModuleSrl;
		$oArg->item_srl = $nItemSrl;
		$output = executeQuery('svitem.getAdminItem', $oArg);
		return $output;
	}
/**
 * @brief [상품목록] 카테고리 관리에서 호출
 **/
	public function getSvitemAdminItemListAjax() 
	{
		$oArg->module_srl = Context::get('module_srl');
		$oArg->page = Context::get('page');
		$oArg->category_node_srl = Context::get('category_srl');
		$output = $this->_getItemList($oArg);
		$this->add('data', $output->data);
	}
/**
 * @brief  svpromotionAdminView::dispSvpromotionAdminItemDiscountList()에서 호출
 **/
	public function getSvitemAdminItemList($oParam)
	{
		$output = $this->_getItemList($oParam);
		return $output;
	}
/**
 * @brief  this->getSvitemAdminItemList(), this->getSvitemAdminItemListAjax()에서 호출
 **/
	private function _getItemList($oParam)
	{
		$oArg = new stdClass();
		if( !is_null($oParam->module_srl) && $oParam->module_srl != 0 )
			$oArg->module_srl = $oParam->module_srl;
		if( !is_null($oParam->category_node_srl) )//&& $oParam->category_node_srl != 0 )
			$oArg->category_node_srl = $oParam->category_node_srl;
		if( !is_null($oParam->page) && $oParam->page != 0 )
			$oArg->page = $oParam->page;
		if( !is_null($oParam->list_count) && $oParam->list_count != 0 )
			$oArg->list_count = $oParam->list_count;
		if( !is_null($oParam->sort_index) && $oParam->sort_index != 0 )
			$oArg->sort_index = $oParam->sort_index;
		if( !is_null($oParam->order_type) && $oParam->order_type != 0 )
			$oArg->order_type = $oParam->order_type;
		if( !is_null($oParam->item_name) && strlen($oParam->item_name) > 0 )
			$oArg->item_name = $oParam->item_name;

//dispSvpromotionAdminItemDiscountList 위해서 임시 유지 시작
		if( is_null($oArg->module_srl) || $oArg->module_srl == 0 )
			$oArg->module_srl = Context::get('module_srl');
		if( !is_null($oParam->page) && $oParam->page != 0 )
			$oArg->page = Context::get('page');
//dispSvpromotionAdminItemDiscountList 위해서 임시 유지 끝
		
		$output = executeQueryArray('svitem.getAdminItemList', $oArg);
		$oSvitemModel = &getModel('svitem');
		$oModuleModel = getModel('module');
		foreach( $output->data as $key=>$val )
		{
			$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($val->module_srl);
			$val->mid = $oModuleInfo->mid;
			$val->review_count = $oSvitemModel->getReviewCnt($val->item_srl);
		}
		return $output;
	}
/**
 * @brief 자식 카테고리 목록 추출
 **/
	private function _getCategoryChildrenList($nModuleSrl,$nNodeSrl) 
	{
		if(is_null($nModuleSrl) || $nModuleSrl == 0)
			return new StdClass();
        $args = new stdClass();
		if(is_null($nNodeSrl))
			$args->parent_srl = 0;
		else
			$args->parent_srl = $nNodeSrl;
		
		$aChild = array();
		$args->module_srl = $nModuleSrl;
		$output = executeQueryArray('svitem.getCategoryNodeList', $args);
		if($output->data) 
		{
			foreach($output->data as $no => $val) 
			{
				$child = new StdClass();
				$child->id = $val->category_node_srl;
				$child->text = $val->category_name;
				$child->children = true;
				$aChild[] = $child;
			}
		}
		return $aChild;
	}
/**
 * @brief
 **/
/*	public function getCombinedExtraVars($oParam)
	{
		switch( $oParam->sPageType )
		{
			case 'catalog':
				$oArgs->sPageType = 'svitem';
				break;
			case 'detail':
				$oArgs->sPageType = 'svitem.detail';
				break;
			default:
				echo __FILE__.':'.__lINE__.'<BR>';
				var_dump( $oArgs);
				echo '<BR><BR>';
				exit;
		}
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
		
		$oArgs->sPageType = 'catalog';
		$oArgs->nModuleSrl = $oParam->nModuleSrl;
		$oRst = $oExtraVarsController->getCombinedExtraVars($oArgs);
		if(!$oRst->toBool()) 
			return $oDetailRst;

		
		$aRst = $oItemExtraVars->getListRegistered($oArgs);
		unset($oArgs);
		unset($oItemExtraVars);
		return $aRst;
	}*/
}
/* End of file svitem.admin.model.php */
/* Location: ./modules/svitem/svitem.admin.model.php */