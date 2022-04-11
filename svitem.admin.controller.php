<?php
/**
 * @class  svitemAdminController
 * @author singleview(root@singleview.co.kr)
 * @brief  svitemAdminController
 */
//require_once _XE_PATH_.'modules/svitem/ext_class/excel/Classes/PHPExcel.php';
require_once _XE_PATH_.'classes/sv_classes/PHPExcel-1.8.0/Classes/PHPExcel.php';
/**
 * @brief enforce PHPExcel to read very long numbers from excel as a pure string
 * https://stackoverflow.com/questions/5753469/problem-reading-numbers-from-excel-with-phpexcel/10651335#10651335?newreg=52cf2bb4607f41e0aae7c470727b59a3
 * https://stackoverflow.com/questions/16233436/phpexcel-read-long-number-from-cell
 * https://scrutinizer-ci.com/g/reginaldojunior/winners/code-structure/master/operation/%2Bglobal%5CPHPExcel_Worksheet%3A%3ArangeToArray
 * PHPExcel이 긴 숫자 문자열을 과학부동소수로 읽지 않으려면 ini_set('precision', '17'); 로 변경해야 함 기본값은 14
 **/
class SpecialValueBinder extends PHPExcel_Cell_DefaultValueBinder implements PHPExcel_Cell_IValueBinder
{
    public function bindValue(PHPExcel_Cell $cell, $value = null)
    {
        if ($cell->getColumn() == 'AJ') {
            $value = PHPExcel_Shared_String::SanitizeUTF8($value);
			$cell->setValueExplicit($value, PHPExcel_Cell_DataType::TYPE_STRING);
            return true;
        }

        // Not bound yet? Use parent...
        return parent::bindValue($cell, $value);
    }
}

class svitemAdminController extends svitem
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
 * @brief 관리자 - 기본설정 저장
 **/
	public function procSvitemAdminConfig() 
	{
		$oArgs = Context::getRequestVars();
		if( is_null( $oArgs->external_server_type ) )
			$oArgs->external_server_type = '';
		
		if( is_null( $oArgs->naver_ep_extract_svitem ) )
			$oArgs->naver_ep_extract_svitem = '';
		if( is_null( $oArgs->daum_ep_extract_svitem ) )
			$oArgs->daum_ep_extract_svitem = '';

		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		$this->setRedirectUrl( getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminConfig','module_srl',Context::get('module_srl')));
	}
/**
 * @brief 
 **/
	public function procSvitemAdminInsertItem() 
	{
		$oArgs = Context::getRequestVars();
		// before
		//$oTriggerRst = ModuleHandler::triggerCall('svitem.insertItem', 'before', $oArgs);
		//if(!$oTriggerRst->toBool())
		//	return $oTriggerRst;
		//unset($oTriggerRst);

		require_once(_XE_PATH_.'modules/svitem/svitem.item_admin.php');
		$oItemAdmin = new svitemItemAdmin();
		$oArgs->mode = 'single';
		$oInsertRst = $oItemAdmin->create($oArgs);
		if (!$oInsertRst->toBool())
			return $oInsertRst;
		
		$nItemSrl = $oInsertRst->get('nItemSrl');
		$this->add('item_srl', $nItemSrl);
		unset($oInsertRst);
		unset($oItemAdmin);

		// after
		//$output = ModuleHandler::triggerCall('svitem.insertItem', 'after', $oArgs);
		//if (!$output->toBool())
		//	return $output;
		//unset($output);
		//$this->_resetCache();
		$this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvitemAdminUpdateItem','module_srl',Context::get('module_srl'),'item_srl',$nItemSrl);
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
	}
/**
 * @brief 엑셀 파일을 이용하여 제품 일괄 등록
 * @return 
 **/
	public function procSvitemAdminBulkInsertViaExcel() 
	{
		$aAllowedExt = array('xls', 'xlsx', 'csv');

		$nError = $_FILES['excel_filename']['error'];
		$sName = $_FILES['excel_filename']['name'];
		// 파일의 저장형식이 utf-8일 경우 한글파일 이름은 깨지므로 euc-kr로 변환해준다.
		$sExt = array_pop(explode('.', $sName));
		// 오류 확인
		if( $nError != UPLOAD_ERR_OK ) 
		{
			switch( $nError ) 
			{
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					echo "파일이 너무 큽니다. ($nError)";
					break;
				case UPLOAD_ERR_NO_FILE:
					echo "파일이 첨부되지 않았습니다. ($nError)";
					break;
				default:
					echo "파일이 제대로 업로드되지 않았습니다. ($nError)";
			}
			exit;
		}
		// 확장자 확인
		if( !in_array($sExt, $aAllowedExt) ) 
			return new BaseObject( -1, 'msg_error_invalid_file_extension');

		if( $_FILES['excel_filename']['type'] != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' && //ms office excel
			$_FILES['excel_filename']['type'] != 'application/vnd.ms-excel' ) //ms office csv
			return new BaseObject(-1, 'msg_no_data_file_attached');

		$objPHPExcel = new PHPExcel();
		require_once(_XE_PATH_.'modules/svitem/svitem.item_admin.php');
		$oItemAdmin = new svitemItemAdmin();
		
		try // 업로드한 PHP 파일을 읽어온다.
		{
			$nModuleSrl = Context::get('module_srl');
			$sImgAbsPath = trim(Context::get('img_abs_path'));
			// PHPExcel이 긴 숫자문자열 컬럼을 과학부동소수로 읽지 않으려면
			// PHPExcel_Cell::setValueBinder( new SpecialValueBinder() ); //  Tell PHPExcel that we want to use our Special Value Binder
			$sTmpName = iconv("UTF-8", "EUC-KR", $_FILES['excel_filename']['tmp_name']);
			$objPHPExcel = PHPExcel_IOFactory::load($sTmpName);
			//$nSheetsCount = $objPHPExcel->getSheetCount();
			// 시트Sheet별로 읽기
			//for($nCurrentSheet = 0; $nCurrentSheet < $nSheetsCount; $nCurrentSheet++) 
			//{
				$nCurrentSheet = 0;
				$objPHPExcel->setActiveSheetIndex($nCurrentSheet);
				$oActivesheet = $objPHPExcel->getActiveSheet();
				$nHighestRow = $oActivesheet->getHighestRow();             // 마지막 행
				$nHighestColumn = $oActivesheet->getHighestColumn();    // 마지막 컬럼

				$aHeader = $oActivesheet->rangeToArray('A1:'.$nHighestColumn.'1', NULL, TRUE, FALSE);
				$aHeaderInfo = array_flip($aHeader[0]);
				
				// 한줄읽기
				for($nRow = 2; $nRow <= $nHighestRow; $nRow++) 
				{
					$aRowData = $oActivesheet->rangeToArray('A'.$nRow.':'.$nHighestColumn.$nRow, NULL, TRUE, FALSE); // $aRowData가 한줄을 셀별로 배열 작성함
					// $aRowData에 들어가는 값은 계속 초기화 되기때문에 값을 담을 새로운 배열을 선안하고 담는다.
					$oArgs->module_srl = $nModuleSrl;
					$oArgs->item_code = trim($aRowData[0][$aHeaderInfo['아이템코드']]);
					$sBarcode = trim($aRowData[0][$aHeaderInfo['바코드']]);
					$oArgs->barcode = $sBarcode;
					$oArgs->item_name = trim($aRowData[0][$aHeaderInfo['제품명']]);
					$oArgs->price = trim($aRowData[0][$aHeaderInfo['판매가']]);
					
					$sImageFilename = $sBarcode.'.jpg';
					$oArgs->thumbnail_image['name'] = $sImageFilename;
					$oArgs->thumbnail_image['type'] = $this->_getImageType($sImageFilename); //'image/jpg';
					$oArgs->thumbnail_image['tmp_name'] = $sImgAbsPath.$sImageFilename;
					$oArgs->thumbnail_image['error'] = 0;
					$oArgs->thumbnail_image['size'] = filesize($oArgs->thumbnail_image['tmp_name']);
					$oInsertRst = $oItemAdmin->create($oArgs);
					if($oInsertRst->toBool())
						echo $sBarcode.' upload succeed<BR>';
					else
					{
						echo $sBarcode.' upload failed<BR>';
						var_dump( $oInsertRst);
						echo '<BR><BR>';
					}
					unset($oInsertRst);
					unset($oArgs);
				}
exit;
			//}		
		} 
		catch(exception $exception) 
		{
			echo $exception;
		}
exit;	
	}
/**
 * @brief 
 **/
	public function procSvitemAdminUpdateItem() 
	{
		$oArgs = Context::getRequestVars();
		// before
		//$oTriggerRst = ModuleHandler::triggerCall('svitem.updateItem', 'before', $oArgs);
		//if (!$oTriggerRst->toBool())
		//	return $oTriggerRst;
		//unset($oTriggerRst);
		
		// update item
		require_once(_XE_PATH_.'modules/svitem/svitem.item_admin.php');
		$oItemAdmin = new svitemItemAdmin();
        $oParams = new stdClass();
		$oParams->item_srl = $oArgs->item_srl;
		$oTmpRst = $oItemAdmin->loadHeader($oParams);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_item_request');
		unset($oParams);
		unset($oTmpRst);
		$oInsertRst = $oItemAdmin->update($oArgs);
		if (!$oInsertRst->toBool())
			return $oInsertRst;
		unset($oInsertRst);
		unset($oItemAdmin);
		
		// after
		//$oTriggerRst = ModuleHandler::triggerCall('svitem.updateItem', 'after', $oArgs);
		//if(!$oTriggerRst->toBool())
		//	return $oTriggerRst;
		//unset($oTriggerRst);

		$this->_resetCache();
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'),'act','dispSvitemAdminUpdateItem','module_srl',Context::get('module_srl'),'item_srl',Context::get('item_srl'),'ua_type', Context::get('ua_type'), 'page', Context::get('page'), 'search_item_name',Context::get('search_item_name'),'category',Context::get('category')));
	}
/**
 * @brief 논리적인 상품 삭제
 * module_srl을 0으로 바꿔서 상품 관리자에서 검색되지 않게함
 **/
	public function procSvitemAdminDeleteItem() 
	{
		// get item info.
		$nItemSrl = Context::get('item_srl');
		if(!$nItemSrl)
			return new BaseObject(-1, 'msg_invalid_item_srl');

		// before
		//$oTriggerRst = ModuleHandler::triggerCall('svitem.deleteItem', 'before', $oArgs);
		//if (!$oTriggerRst->toBool())
		//	return $oTriggerRst;
		//unset($oTriggerRst);
		
		require_once(_XE_PATH_.'modules/svitem/svitem.item_admin.php');
		$oItemAdmin = new svitemItemAdmin();
		$oArgs->nItemSrl = $nItemSrl;
		$oTmpRst = $oItemAdmin->loadHeader($oArgs);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_item_request');
		unset($oArgs);
		unset($oTmpRst);
		$oInsertRst = $oItemAdmin->deactivate();
		if (!$oInsertRst->toBool())
			return $oInsertRst;
		unset($oInsertRst);
		unset($oItemAdmin);
		
		// after
		//$oTriggerRst = ModuleHandler::triggerCall('svitem.deleteItem', 'after', $oArgs);
		//if(!$oTriggerRst->toBool())
		//	return $oTriggerRst;
		//unset($oTriggerRst);

		$this->setMessage('success_deleted');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminItemListByModule','module_srl',Context::get('module_srl'));
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief 상품 영구 삭제; 코드 블록만 유지하고 이 메소드의 접근을 차단함
 **/
	public function procSvitemAdminPermanentDeleteItem() 
	{
		$nItemSrl = Context::get('item_srl');
		if(!$nItemSrl)
			return new BaseObject(-1, 'msg_invalid_item_srl');

		require_once(_XE_PATH_.'modules/svitem/svitem.item_admin.php');
		$oItemAdmin = new svitemItemAdmin();
		$oArgs->nItemSrl = $nItemSrl;
		$oTmpRst = $oItemAdmin->loadHeader($oArgs);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_item_request');
		unset($oArgs);
		unset($oTmpRst);
		$oInsertRst = $oItemAdmin->remove();
		if (!$oInsertRst->toBool())
			return $oInsertRst;
		unset($oInsertRst);
		unset($oItemAdmin);

		$this->setMessage('success_deleted');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminItemListByModule','module_srl',Context::get('module_srl'));
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief 아이템 목록 화면의 [수정사항적용] 버튼
 **/
	public function procSvitemAdminUpdateItemList() 
	{
		$nModuleSrl = Context::get('module_srl');
		$aItemSrl = Context::get('item_srls');
		$aItemName = Context::get('item_name');
		$aItemCode = Context::get('item_code');
		$aDisplay = Context::get('display');
		$aListOrder = Context::get('list_order');
		$aPrice = Context::get('price');
		if(count($aItemSrl)) // update item
		{
			$aUpdatedItem = [];
			$oArg = new stdClass();
			require_once(_XE_PATH_.'modules/svitem/svitem.item_admin.php');
			$oItemAdmin = new svitemItemAdmin();
			foreach($aItemSrl as $nIdx => $nItemSrl) 
			{
                $oParams = new stdClass();
				$oParams->item_srl = $nItemSrl;
				$oTmpRst = $oItemAdmin->loadHeader($oParams);
				if(!$oTmpRst->toBool())
					return new BaseObject(-1,'msg_invalid_item_request');
				unset($oParams);
				unset($oTmpRst);
                $oParams = new stdClass();
				$oParams->sUaType = 'og';
				$oTmpRst = $oItemAdmin->loadDetail($oParams);
				$oArg->item_srl = null;
				$oArg->item_name = null;
				$oArg->item_code = null;
				$oArg->display = null;
				$oArg->list_order = null;
				$oArg->price = null;

				//$oHeaderInfo = $oItemAdmin->getHeader('old'); // 기존 정보만 가져오기
				$bUpdated = FALSE;
				if($aItemName[$nIdx] != $oItemAdmin->item_name)
				{
					$oArg->item_name = $aItemName[$nIdx];
					$bUpdated = TRUE;
				}
				if($aItemCode[$nIdx] != $oItemAdmin->item_code)
				{
					$oArg->item_code = $aItemCode[$nIdx];
					$bUpdated = TRUE;
				}
				if($aDisplay[$nIdx] != $oItemAdmin->display)
				{
					$oArg->display = $aDisplay[$nIdx];
					$bUpdated = TRUE;
				}
				if($aListOrder[$nIdx] != $oItemAdmin->list_order)
				{
					$oArg->list_order = $aListOrder[$nIdx];
					$bUpdated = TRUE;
				}
				if($aPrice[$nIdx] != $oItemAdmin->price)
				{
					$oArg->price = $aPrice[$nIdx];
					$bUpdated = TRUE;
				}
				if($bUpdated) // commit update
				{
					$oArg->item_srl = $nItemSrl;
					$oArg->updatetime = date('YmdHis');
					$oInsertRst = $oItemAdmin->update($oArg);
					if (!$oInsertRst->toBool())
						return $oInsertRst;
					unset($oInsertRst);
					$aUpdatedItem[] = $oItemAdmin->item_name;
				}
			}
			unset($oItemAdmin);
		}
		$this->_resetCache();
		$sUpdatedItemName = implode(',', $aUpdatedItem);
		$this->setMessage($sUpdatedItemName.' 품목이 변경되었습니다.'); // 실제로 변경된 품목만 추출하도록 개선해야함
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module')
				,'act', 'dispSvitemAdminItemListByModule','module_srl',Context::get('module_srl'),'page',Context::get('page')
				,'category',Context::get('category'),'search_item_name',Context::get('search_item_name'));
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief update display category list order
 **/
	public function procSvitemAdminUpdateDCListOrder() 
	{
		$order = Context::get('order');
		parse_str($order);
		$idx = 1;
		if(is_array($record)) 
		{
			foreach ($record as $category_srl) 
			{
				$args->category_srl = $category_srl;
				$args->list_order = $idx;
				$output = executeQuery('svitem.updateDisplayCategoryListOrder', $args);
				if (!$output->toBool())
					return $output;
				$idx++;
			}
		}
	}
/**
 * @brief update display item list order
 **/
	public function procSvitemAdminUpdateDIListOrder() 
	{
		$order = Context::get('order');
		parse_str($order);
		$idx = 1;
		if(is_array($record)) 
		{
			foreach ($record as $item_srl)
			{
				$args->item_srl = $item_srl;
				$args->list_order = $idx;
				$output = executeQuery('svitem.updateDisplayItemListOrder', $args);
				if(!$output->toBool())
					return $output;
				$idx++;
			}
		}
	}
/**
 * @brief 
 */
	public function procSvitemAdminMoveCatalog() 
	{
		$logged_info = Context::get('logged_info');
		if (!$logged_info)
			return new BaseObject(-1, 'msg_log_required');

		$parent_id = Context::get('parent_id');
		$node_id = Context::get('node_id');
		$target_id = Context::get('target_id');
		$position = Context::get('position');

		$this->_moveNode($node_id, $parent_id);

		if ($position=='next')
		{
			$output = $this->_moveNodeToNext($node_id, $parent_id, $target_id);
			if (!$output->toBool())
				return $output;
		}
		if ($position=='prev') 
		{
			$output = $this->_moveNodeToPrev($node_id, $parent_id, $target_id);
			if (!$output->toBool())
				return $output;
		}
	}
/**
 * @brief 
 **/
	public function procSvitemAdminUpdateExtmallLogger() 
	{
		$nItemSrl = (int)Context::get('item_srl');
		$sMode = Context::get('mode');
		if(!$nItemSrl || !$sMode)
			return new BaseObject(-1, 'msg_invalid_request');
		
		switch( $sMode )
		{
			case 'daily_log':
				$output = $this->_registerExtmallLoggerDaily();
				break;
			case 'config':
				$output = $this->_updateExtmallLoggerConfig($nItemSrl);
				break;
			case 'register_extmall':
				$output = $this->_registerExtmallLogger($nItemSrl);
				break;
		}

		if(!$output->toBool())
			return $output;

		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'),'act','dispSvitemAdminExtMallLogger','module_srl',Context::get('module_srl'),'item_srl',$nItemSrl));
	}
/**
 * @brief admin view [상품목록] 상품기본분류 아이템 등록/해제 버튼 처리
 * 등록 인수: item_srls, catalog_node_srl 
 * 해제 인수: item_srl 
 **/
	public function procSvitemAdminUpdateItemDefaultCatalogInfo() 
	{
		$nModuleSrl = (int)Context::get('module_srl');
		$sActionMode = Context::get('mode');
		if(!$nModuleSrl || strlen( $sActionMode)==0)
			return new BaseObject(-1, 'msg_invalid_request');
		$args->module_srl = $nModuleSrl;
		if( $sActionMode == 'withdraw')
		{
			$nItemSrl = Context::get('item_srl');
			if( is_null( $nItemSrl ) )
				return new BaseObject(-1, 'msg_invalid_request');
			$args->item_srl = (int)$nItemSrl;
			$args->category_node_srl = 0;
			$output = executeQuery('svitem.updateAdminItem', $args);
			if(!$output->toBool())
				return $output;
		}
		else if( $sActionMode == 'invite')
		{
			$aItemSrl = Context::get('item_srls');
			$nCategoryNodeSrl = (int)Context::get('catalog_node_srl');
			if( !strlen( $nCategoryNodeSrl ) || !count( $aItemSrl ))
				return new BaseObject(-1, 'msg_invalid_request');

			$args->category_node_srl = $nCategoryNodeSrl;
			$oSvitemAdminModel = &getAdminModel('svitem');
			$nAlreadyClassifiedItemCnt = 0;
			foreach( $aItemSrl as $key=>$val)
			{
				// check the item is already classified
				$output = $oSvitemAdminModel->getSvitemAdminItemInfo($nModuleSrl,$val);
				if(!$output->toBool())
					return $output;
				if( $output->data->category_node_srl != 0)
				{
					$nAlreadyClassifiedItemCnt++;
					$sAlreadyClassifiedItemName .= '"'.$output->data->item_name.'",';
					continue;
				}
				$args->item_srl = $val;
				$output = executeQuery('svitem.updateAdminItem', $args);
				if(!$output->toBool())
					return $output;
			}
			if( $nAlreadyClassifiedItemCnt )
				$this->add('notice', sprintf(Context::getLang('msg_item_already_classified'), $sAlreadyClassifiedItemName));
		}
		$this->_resetCache();
	}
/**
 * @brief [상품목록] 메뉴에 jstree 1.3.3 node update
 **/
	public function procSvitemAdminUpdateCatalogNodeAjax()
	{
		$sMode = Context::get('mode');
		switch($sMode)
		{
			case 'insert':
				$output = $this->_insertCatalogNodeAjax();
				break;
			case 'delete':
				$output = $this->_deleteCatalogNodeAjax();
				break;
			case 'rename':
				$output = $this->_renameCatalogNodeAjax();
				break;
			default:
				break;
		}
		if(!$output->toBool())
			return $output;
		
		$this->_resetCache();
		$this->setMessage( 'success_updated' );
	}
/**
 * @brief admin view [상품목록] show window tab에 추가/삭제 버튼 처리
 **/
	public function procSvitemAdminUpdateItemShowWindowTab() 
	{
		$nModuleSrl = (int)Context::get('module_srl');
		$sActionMode = Context::get('mode');
		if(!$nModuleSrl || strlen( $sActionMode)==0)
			return new BaseObject(-1, 'msg_invalid_request');
		
		$args->module_srl = $nModuleSrl;
		if( $sActionMode == 'withdraw')
		{
			$nModuleSrl = (int)Context::get('module_srl');
			$nCategorySrl = (int)Context::get('show_window_tab_srl');
			$nItemSrl = (int)Context::get('item_srl');
			if(!$nModuleSrl || !$nItemSrl)
				return new BaseObject(-1, 'msg_invalid_request');
			
			$args->module_srl = $nModuleSrl;
			$args->category_srl = $nCategorySrl;
			$args->item_srl = $nItemSrl;
			$output = executeQuery('svitem.deleteAdminShowWindowItem', $args);
			if(!$output->toBool())
				return $output;
		}
		else if( $sActionMode == 'invite')
		{
			$aItemSrl = Context::get('item_srls');
			$nCategoryNodeSrl = (int)Context::get('show_window_srl');
			if( !strlen( $nCategoryNodeSrl ) || !count( $aItemSrl ))
				return new BaseObject(-1, 'msg_invalid_request');

			$args->category_srl = $nCategoryNodeSrl;
			foreach( $aItemSrl as $key=>$val)
			{
				$args->item_srl = $val;
				// check the item is already inserted
				$output = executeQuery('svitem.getAdminShowWindowItemByItemSrl', $args);
				if( count( $output->data ) > 0 )
					continue;
				$output = executeQuery('svitem.insertAdminShowWindowItem', $args);
				if(!$output->toBool())
					return $output;
			}
		}
		$this->_resetCache();
	}
/**
 * @brief ./tpl/show_window_catalog.html에서 사용
 **/
	public function procSvitemAdminUpdateShowWindowCatalog() 
	{
		$sActionMode = Context::get('mode');
		var_dump('ddd');
		switch($sActionMode)
		{
			case 'insert':
				$output = $this->_insertShowWindowCatalog();
				if(!$output->toBool())
					return $output;
				if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
				{
					$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminShowWindowCatalog','module_srl',Context::get('module_srl'));
					$this->setRedirectUrl($returnUrl);
				}
				break;
			case 'update':
				$output = $this->_updateShowWindowCatalog();
				if(!$output->toBool())
					return $output;
				if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
				{
					$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminShowWindowCatalog','module_srl',Context::get('module_srl'));
					$this->setRedirectUrl($returnUrl);
				}
				break;
			case 'delete':
				$output = $this->_deleteShowWindowCatalog();
				if(!$output->toBool())
					return $output;
				break;
		}
		$this->_resetCache();
		return;
	}
/**
 * @brief insert QNA board
 * item_Srl을 기준으로 QNA게시판 카테고리ID를 저장
 **/
	public function procSvitemAdminUpdateQnaBoardMgmt()
	{
		$oTempArgs = Context::getRequestVars();
		$aQnaItemMatch = array();
		foreach( $oTempArgs as $key=>$val)
		{
			if(strpos($key, 'qna_connect_') !== false)
			{
				$nItemSrl = str_replace('qna_connect_', '', $key);
				foreach( $val as $rev_key=>$rev_val)
					$aItemQnaMatch[$nItemSrl][$rev_val] = 'match';
			}
		}
		$oArgs = new stdClass();
		$oArgs->qna_for_item = $aItemQnaMatch;
		$oArgs->order_target = $oTempArgs->order_target;
		$oArgs->order_type = $oTempArgs->order_type;
		$nConnectedQnaBoard = (int)$oTempArgs->connected_qna_board_srl;
		if( is_null( $nConnectedQnaBoard ) )
			$nConnectedQnaBoard = 0;
		
		$oArgs->max_qnas_cnt = $oTempArgs->max_qnas_cnt;
		$oArgs->qnas_per_page = $oTempArgs->qnas_per_page;
		$oArgs->connected_qna_board_srl = $nConnectedQnaBoard;
		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminQnaBoardMgmt' ));
	}
/**
 * @brief insert review board
 * item_Srl을 기준으로 리뷰게시판 카테고리ID를 저장
 **/
	public function procSvitemAdminUpdateReviewBoardMgmt()
	{
		$oTempArgs = Context::getRequestVars();
		$aReviewItemMatch = array();
		foreach( $oTempArgs as $key=>$val)
		{
			if(strpos($key, 'review_connect_') !== false)
			{
				$nItemSrl = str_replace('review_connect_', '', $key);
				foreach( $val as $rev_key=>$rev_val)
					$aItemReviewMatch[$nItemSrl][$rev_val] = 'match';// echo $rev_val.'<BR>';
			}
		}
		$oArgs = new stdClass();
		$oArgs->review_for_item = $aItemReviewMatch;
		$oArgs->order_target = $oTempArgs->order_target;
		$oArgs->order_type = $oTempArgs->order_type;
		$nConnectedReviewBoard = (int)$oTempArgs->connected_review_board_srl;
		if( is_null( $nConnectedReviewBoard ) )
			$nConnectedReviewBoard = 0;

		$oArgs->max_reviews_cnt = $oTempArgs->max_reviews_cnt;
		$oArgs->reviews_per_page = $oTempArgs->reviews_per_page;
		$oArgs->connected_review_board_srl = $nConnectedReviewBoard;
		$output = $this->_saveModuleConfig($oArgs);
		if(!$output->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminReviewBoardMgmt' ));
	}
/**
 * @brief mid 생성하거나 변경
 **/
	public function procSvitemAdminInsertModInst()
	{
		$oArgs = Context::getRequestVars();
		$oArgs->module = 'svitem';
		// connected_svcart_module_srl_hidden 변수를 자동 추가함
		$oModuleModel = &getModel('module');
		$oSvcartMidConfig = $oModuleModel->getModuleInfoByMid($oArgs->svcart_mid);
		$oArgs->connected_svcart_module_srl_hidden = $oSvcartMidConfig->module_srl;
		unset($oSvcartMidConfig);

		// module_srl의 값에 따라 insert
		if(!$oArgs->module_srl) 
		{
			$oModuleController = &getController('module');
			$oRst = $oModuleController->insertModule($oArgs);
			$nModuleSrl = $oRst->get('module_srl');
			$sMsgCode = 'success_registed';
		}
		else //update
		{
			$oRst = $this->_updateMidLevelConfig($oArgs);
			$nModuleSrl = $oArgs->module_srl;
			$sMsgCode = 'success_updated';
		}
		if(!$oRst->toBool())
			return $oRst;
		
		unset($oRst);
		unset($oArgs);
		$this->add('module_srl', $nModuleSrl);
		$this->setMessage($sMsgCode);
		$sReturnUrl = getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminInsertModInst','module_srl',$nModuleSrl);
		$this->setRedirectUrl($sReturnUrl);
	}
/**
 * @brief MID 삭제
 **/
	public function procSvitemAdminDeleteModInst()
	{
		$nModuleSrl = Context::get('module_srl');
		$oModuleController = &getController('module');
		$oRst = $oModuleController->deleteModule($nModuleSrl);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		unset($oModuleController);
		$this->setMessage('success_deleted');
		$sReturnUrl = getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminModInstList', 'page', Context::get('page'));
		$this->setRedirectUrl($sReturnUrl);
	}
/**
 * @brief 확장 변수 추가
 **/
	public function procSvitemAdminInsertExtendVar() 
	{
        $oArgs = new stdClass();
		$oArgs->module_srl = Context::get('module_srl');
		$oArgs->extra_srl = Context::get('extra_srl');
		$oArgs->column_type = Context::get('column_type');
		$oArgs->column_name = strtolower(Context::get('column_name'));
		$oArgs->column_title = Context::get('column_title');
		$oArgs->default_value = explode("\n", str_replace("\r", '', Context::get('default_value')));
		$oArgs->required = Context::get('required');
		$oArgs->is_active = (isset($oArgs->required));
		$oArgs->description = Context::get('description');
		if(!in_array(strtoupper($oArgs->required), array('Y','N')))
			$oArgs->required = 'N';

		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
		$oRst = $oExtraVarsController->addExtendedVar($oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		unset($oArgs);
		unset($oRst);
		unset($oExtraVarsController);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminExtendVarSetup');
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
	}
/**
 * @brief 확장 변수 삭제
 **/
	public function procSvitemAdminDeleteExtendVar() 
	{
		$nModuleSrl = Context::get('module_srl');
        $oArgs = new stdClass();
		$oArgs->nModuleSrl = $nModuleSrl;
		$oArgs->nExtraSrl = Context::get('extra_srl');
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
		$oRst = $oExtraVarsController->removeExtendedVar($oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		unset($oArgs);
		unset($oRst);
		unset($oExtraVarsController);
		$this->setMessage('success_deleted');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
			$this->setRedirectUrl(getNotencodedUrl('','module','svshopmaster','act','dispSvitemAdminExtendVarSetup','module_srl',$nModuleSrl));
	}
/**
 * @brief ./tpl/js/itemextrasetup.js에서 호출
 * ./svitem.extravar.controller.php로 이동
 **/
	public function procSvitemAdminUpdateItemExtraOrder() 
	{
		$order = Context::get('order');
		parse_str($order);
		$idx = 1;
		if(is_array($record))
		{
			foreach ($record as $extra_srl)
			{
				$args->extra_srl = $extra_srl;
				$args->list_order = $idx;
				$output = executeQuery('svitem.updateItemExtraOrder', $args);
				if(!$output->toBool())
					return $output;
				$idx++;
			}
		}
	}
/**
 * @brief ./svitem.extravar.controller.php로 이동
 **/
	public function procSvitemAdminUpdateItemListOrder() 
	{
		$order = Context::get('order');
		parse_str($order);
echo __FILE__.':'.__lINE__.'<BR>';
var_dump( $order);
echo '<BR><BR>';
exit;
		$idx = 1;
		if(is_array($record))
		{
			foreach ($record as $item_srl)
			{
				$args->item_srl = $item_srl;
				$args->list_order = $idx;
				$output = executeQuery('svitem.updateItemListOrder', $args);
				if(!$output->toBool())
					return $output;
				$idx++;
			}
		}
	}
/**
 * @brief 
 **/
	public function procSvitemAdminInsertDeliveryInfo() 
	{
		$args->module_srl = Context::get('module_srl');
		$args->item_srl = Context::get('item_srl');
		$args->delivery_info = Context::get('delivery_info');
		// Fix if item_srl exists. Add if not exists.
		if(!$args->item_srl)
			return new BaseObject(-1, 'msg_invalid_item');
		else
		{
			$output = executeQuery('svitem.updateItem', $args);
			if(!$output->toBool())
				return $output;
		}
		$this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminUpdateItem');
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief 화면 표시 내용 설정 저장
 * 'catalog' 목록 페이지 'detail' 상세 페이지
 */
	public function procSvitemAdminSaveDisplayConfig()
	{
        $oParam = new stdClass();
		$oParam->nModuleSrl = Context::get('module_srl');
		$sPageType = Context::get('page_type'); //'catalog';
		$oParam->sPageType = $sPageType;
		$this->_registerExtScript($oParam);
		$oRst = $this->_insertDisplayConfig($oParam);
		if(!$oRst->toBool())
			return $oRst;
		unset($oParam);
		unset($oRst);
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url'));
	}
/**
 * @brief 
 **/
	public function procSvitemAdminInsertBundling()
	{
		$item_srl = Context::get('item_srl');
		if (!$item_srl)
			return new BaseObject(-1, 'msg_invalid_request');

		$bundle_srls = Context::get('bundle_srls');
		$bundlling_items = Context::get('bundlling_items');
		$bundle_price = Context::get('bundle_price');
		$bundle_quantity = Context::get('bundle_quantity');
		$oSvitemModel = &getModel('svitem');
		$existing_bundles = $oSvitemModel->getBundlings($item_srl);

		foreach( $bundlling_items as $key=>$val )
		{
			if( !$val )
				continue;

			$args->bundle_srl = $bundle_srls[$key];
			if (!$args->bundle_srl)
			{
				$args->bundle_srl = getNextSequence();
				$args->item_srl = $item_srl;
				$args->list_order = $args->bundle_srl * -1;
				$args->bundle_items = trim( $val );
				$args->bundle_price = $bundle_price[$key];
				$args->bundle_quantity = $bundle_quantity[$key];
				$output = executeQuery('svitem.insertBundle', $args);
				if (!$output->toBool())
					return $output;
			}
			else
			{
				$args->bundle_items = trim( $val );
				$args->bundle_price = $bundle_price[$key];
				$args->bundle_quantity = $bundle_quantity[$key];
				$output = executeQuery('svitem.updateBundle', $args);
				if (!$output->toBool())
					return $output;
				unset($existing_bundles[$args->bundle_srl]);
			}
		}
		if (count($existing_bundles))
		{
			$args->bundle_srl = array_keys($existing_bundles);
			$output = executeQuery('svitem.deleteBundle', $args);
			if (!$output->toBool())
				return $output;
		}
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module','svshopmaster','act','dispSvitemAdminUpdateItem','module_srl',Context::get('module_srl'),'item_srl',$item_srl);
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief item class로 set option 이동해야 함
 **/
	public function procSvitemAdminInsertOptions()
	{
		$oSvitemModel = &getModel('svitem');
		$item_srl = Context::get('item_srl');
		if (!$item_srl)
			return new BaseObject(-1, 'msg_invalid_request');
		$option_srls = Context::get('option_srls');
		$options_title = Context::get('options_title');
		$options_price = Context::get('options_price');
		$existing_options = $oSvitemModel->getOptions($item_srl);
		foreach ($options_title as $key=>$val)
		{
			if (!$val)
				continue;
			$args->option_srl = $option_srls[$key];
			if (!$args->option_srl)
			{
				$args->option_srl = getNextSequence();
				$args->item_srl = $item_srl;
				$args->list_order = $args->option_srl * -1;
				$args->title = $val;
				$args->price = $options_price[$key];
				$output = executeQuery('svitem.insertOption', $args);
				if (!$output->toBool())
					return $output;
			}
			else
			{
				$args->item_srl = $item_srl;
				$args->list_order = $args->option_srl * -1;
				$args->title = $val;
				$args->price = $options_price[$key];
				$output = executeQuery('svitem.updateOption', $args);
				if (!$output->toBool())
					return $output;
				unset($existing_options[$args->option_srl]);
			}
		}
		if (count($existing_options))
		{
			$args->option_srl = array_keys($existing_options);
			$output = executeQuery('svitem.deleteOptions', $args);
			if (!$output->toBool())
				return $output;
		}

		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module','svshopmaster','act','dispSvitemAdminUpdateItem','module_srl',Context::get('module_srl'),'item_srl',$item_srl);
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * Set barcode ddl 설정
 * @return void
 */
	public function procSvitemAdminBarcodeMgmt()
	{
		$oArgs = Context::gets('barcode_srl', 'barcode_type_name', 'ddl_titles', 'ddl_length', 'ddl_cost_tag');
		if( !$oArgs->barcode_srl )
			$nBarcodeSrl = getNextSequence();
		else
			$nBarcodeSrl = $oArgs->barcode_srl;

		$oSvitemModel = getModel('svitem');
		$oModuleConfig = $oSvitemModel->getModuleConfig();
		$aDdlGroup = $oModuleConfig->aBarcodeDdlGroup;

		$aDdlDefinition = [];
		foreach($oArgs->ddl_titles as $nIdx => $sDdlTitle)
		{
			if( strlen(trim($sDdlTitle))==0 || !(int)$oArgs->ddl_length[$nIdx] )
				continue;
			$aDdlDefinition[$nIdx]->title = $sDdlTitle;
			$aDdlDefinition[$nIdx]->length = $oArgs->ddl_length[$nIdx];
			$aDdlDefinition[$nIdx]->cost_tag = $oArgs->ddl_cost_tag[$nIdx];
		}
		$aDdlGroup[$nBarcodeSrl]->sBarcodeTypeName = $oArgs->barcode_type_name;
		$aDdlGroup[$nBarcodeSrl]->aDdlDefinition = $aDdlDefinition;

//var_dump($aDdlGroup);
//echo '<BR><BR>';
		
		$oTmpArgs->aBarcodeDdlGroup = $aDdlGroup;
//var_dump( $oArgs);
//echo '<BR><BR>';
//exit;
		$oRst = $this->_saveModuleConfig($oTmpArgs);
		unset($oTmpArgs);
		$this->setMessage(Context::getLang('success_updated'));
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvitemAdminBarcodeMgmt', 'barcode_srl', $nBarcodeSrl);
			$this->setRedirectUrl($sReturnUrl);
		}
	}
/**
 * @brief 
 **/
	private function _getImageType($sImgFilename) 
	{
		//$filename = basename($sImgFilename);
		$sImgFileExtension = strtolower(substr(strrchr($sImgFilename,'.'),1));

		switch( $sImgFileExtension ) 
		{
			case 'gif': return 'image/gif';
			case 'png': return 'image/png';
			case 'jpeg':
			case 'jpg': return 'image/jpeg';
			case 'svg': return 'image/svg+xml';
			default:
		}
	}
/**
 * @brief $this->procSvitemAdminSaveDisplayConfig()에서 호출
 * $oParam->sPageType: 'catalog' 'detail'
 **/
	private function _insertDisplayConfig($oParam)
	{
		$nModuleSrl = $oParam->nModuleSrl;
		$sPageType = $oParam->sPageType;
		
		require_once(_XE_PATH_.'modules/svitem/svitem.extravar.controller.php');
		$oExtraVarsController = new svitemExtraVarController();
        $oArgs = new stdClass();
		$oArgs->nModuleSrl = $nModuleSrl;
		$oArgs->sList = Context::get('list');
		$oArgs->sPageType = $sPageType;
		$oRst = $oExtraVarsController->saveExtraVars($oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		unset($oArgs);
		unset($oRst);
		unset($oExtraVarsController);
		
		if( $sPageType == 'catalog' )
		{
			if($nModuleSrl)
			{
				$oArgs = Context::getRequestVars();
				return $this->_updateMidLevelConfig($oArgs);
			}
			else
				return new BaseObject(-1, 'msg_invalid_module_srl1');
		}
		elseif( $sPageType == 'detail' )
		{
			if($nModuleSrl)
			{
				$oArgs = Context::getRequestVars();
				return $this->_updateMidLevelConfig($oArgs);
			}
			else
				return new BaseObject(-1, 'msg_invalid_module_srl1');
		}
		return new BaseObject();
	}
/**
 * @brief $this->procSvitemAdminSaveDisplayConfig()에서 호출
 */
	private function _registerExtScript($oParam)
	{
		$aScriptPageConv = ['catalog'=>'list_page', 'detail'=>'detail_page'];
		$nModuleSrl = $oParam->nModuleSrl;
		if( !$nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_module_srl');
		
		$sPageType = $aScriptPageConv[$oParam->sPageType];
		switch($sPageType)
		{
			case 'list_page':
			case 'detail_page':
				break;
			default:
				return null;
		}

		$sExtScript = trim(Context::get('ext_script'));
		$sExtScriptFile = _XE_PATH_.'files/svitem/ext_script_'.$sPageType.'_'.$nModuleSrl.'.html';

		if(!$sExtScript)
			FileHandler::removeFile($sExtScriptFile);

		// check agreement value exist
		if($sExtScript)
			$output = FileHandler::writeFile($sExtScriptFile, htmlspecialchars_decode($sExtScript));
	}
/**
* @brief update mid level config
* procSvitemAdminInsertModInst 와 병합해야 함
**/
	private function _updateMidLevelConfig($oArgs)
	{
		if(!$oArgs->module_srl)
			return new BaseObject(-1, 'msg_invalid_module_srl');

		unset($oArgs->module);
		unset($oArgs->error_return_url);
		unset($oArgs->success_return_url);
		unset($oArgs->act);
		unset($oArgs->ext_script);
		unset($oArgs->list);

		$oModuleModel = &getModel('module');
		$oConfig = $oModuleModel->getModuleInfoByModuleSrl($oArgs->module_srl);
		foreach($oArgs as $key=>$val)
			$oConfig->{$key} = $val;
		$oModuleController = &getController('module');
		$oRst = $oModuleController->updateModule($oConfig);
		return $oRst;
	}
/**
* @brief arrange and save module level config
**/
	private function _saveModuleConfig($oArgs)
	{
		$oSvitemAdminModel = &getAdminModel('svitem');
		$oConfig = $oSvitemAdminModel->getModuleConfig();
		foreach( $oArgs as $key=>$val)
			$oConfig->{$key} = $val;

		$oModuleControll = getController('module');
		$output = $oModuleControll->insertModuleConfig('svitem', $oConfig);
		return $output;
	}
/**
 * @brief ./tpl/show_window_catalog.html에서 사용
 **/
	private function _insertShowWindowCatalog()
	{
		$args = Context::gets('catalog_srl','module_srl','category_name','thumbnail_width','thumbnail_height','num_columns','num_rows');
		if(!$args->category_srl) 
		{
			$args->category_srl = getNextSequence();
			$args->list_order = $args->category_srl;
			$output = executeQuery('svitem.insertAdminShowWindowCatalog', $args);
		} 
		else 
			$output = executeQuery('svitem.updateShowWindowCatalog', $args);
		return $output;
	}
/**
 * @brief ./tpl/show_window_catalog.html에서 사용
 **/
	private function _deleteShowWindowCatalog() 
	{
		$nCatalogSrl = Context::get('catalog_srl');
        $args = new stdClass();
		$args->category_srl = $nCatalogSrl;
		$output = executeQuery('svitem.deleteShowWindowCatalog', $args);
		if(!$output->toBool())
			return $output;
		$args->category_srl = $nCatalogSrl;
		$output = executeQuery('svitem.deleteAdminShowWindowItems', $args);
		return $output;
	}
/**
 * @brief
 **/
	private function _updateShowWindowCatalog() 
	{
		$args->category_srl = Context::get('category_srl');
		$args->category_name = Context::get('category_name');
		$args->thumbnail_width = Context::get('thumbnail_width');
		$args->thumbnail_height = Context::get('thumbnail_height');
		$args->num_columns = Context::get('num_columns');
		$args->num_rows = Context::get('num_rows');
		return executeQuery('svitem.updateDisplayCategory', $args);
	}
/**
 * @brief 
 **/
	private function _registerExtmallLoggerDaily() 
	{
		$aExtMallSrl = Context::get('ext_mall_srl');
		$aExtMallRsp = Context::get('extmall_rsp');
		$aExtMallQty = Context::get('extmall_bundle_qty');
		$aExtMallPromo = Context::get('extmall_promo_memo');

		foreach( $aExtMallSrl as $key=>$val )
		{
			$nExtMallRsp = (int)$aExtMallRsp[$key];
			if( !$nExtMallRsp )
				continue;

			$args->ext_mall_srl = (int)$aExtMallSrl[$key];
			$args->extmall_rsp = $nExtMallRsp;
			$args->extmall_bundle_qty = (int)$aExtMallQty[$key];
			$args->extmall_promo_memo = trim($aExtMallPromo[$key]);

			//$output = executeQuery('svitem.insertAdminExtmallLog', $args);

var_dump($args);
echo '<BR>';
		}
exit;
		if(!$sExtMallType || !$sExtMallTitle || !$sExtMallUrl)
			return new BaseObject(-1, 'msg_invalid_request');
			
		return $output;
	}
/**
 * @brief 
 **/
	private function _registerExtmallLogger($nItemSrl) 
	{
		$sExtMallType = trim(Context::get('extmall_type'));
		$sExtMallTitle = trim(Context::get('extmall_title'));
		$sExtMallUrl = trim(Context::get('extmall_url'));
		$sExtMallActive = trim(Context::get('is_active'));

		if(!$sExtMallType || !$sExtMallTitle || !$sExtMallUrl)
			return new BaseObject(-1, 'msg_invalid_request');

		if( strlen( $sExtMallActive ) == 0 )
			$sExtMallActive = 'Y';
		
		$args->item_srl = $nItemSrl;
		$args->type = $sExtMallType;
		$args->title = $sExtMallTitle;
		$args->url = $sExtMallUrl;
		$args->is_active = $sExtMallActive;

		$output = executeQuery('svitem.insertAdminExtmall', $args);
		return $output;
	}
/**
 * @brief 
 **/
	private function _updateExtmallLoggerConfig($nItemSrl) 
	{
		$nModuleSrl = (int)Context::get('module_srl');
		if(!$nModuleSrl)
			return new BaseObject(-1, 'msg_invalid_request');

		$sExtMallToggle = Context::get('extmall_logger_toggle');
		if( strlen( $sExtMallToggle ) == 0 )
			$sExtMallToggle = 'off';

		//$oSvitemModel = &getModel('svitem');
		//$oExtraVars = $oSvitemModel->getExtraVars($nModuleSrl);

		// extra_vars에 등록
		$oExtMallConfig = new stdClass();
		$oExtMallConfig->toggle = $sExtMallToggle;
		$args->extmall_log_conf = serialize($oExtMallConfig);
		
		$args->module_srl = $nModuleSrl;
		$args->item_srl = $nItemSrl;
		$output = executeQuery('svitem.updateAdminItemExtmallLogConfig', $args);
		return $output;
	}
/**
 * @brief 
 */
	private function _moveNode($node_id, $parent_id)
	{
		$logged_info = Context::get('logged_info');
		if (!$logged_info)
			return;
		// get destination
		if (in_array($parent_id, array('f.','t.','s.'))) 
			$dest_route = $parent_id;
		else
		{
			$args->node_id = $parent_id;
			$output = executeQuery('svitem.getCategoryInfo', $args);
			if (!$output->toBool())
				return $output;
			$dest_node = $output->data;
			$dest_route = $dest_node->node_route . $dest_node->node_id . '.';
			$route_text = Context::getLang('category') . ' > ' . $output->data->category_name;
		}
		// new route
		$new_args->node_id = $node_id;
		$new_args->node_route = $dest_route;
		$new_args->node_route_text = $route_text;
		$new_args->list_order = $parent_id + 1;
		// update children
		$args->node_id = $node_id;
		$output = executeQuery('svitem.getCategoryInfo', $args);
		$route_text = $route_text.' > '.$output->data->category_name;
		if (!$output->toBool())
			return $output;

		$search_args->node_route = $output->data->node_route . $output->data->node_id.'.';
		$output = executeQueryArray('svitem.getCategoryInfoByNodeRoute', $args);
		if (!$output->toBool())
			return $output;

		$old_route = $search_args->node_route;
		$new_route = $new_args->node_route.$node_id.'.';
		if ($output->data)
		{
			foreach ($output->data as $no => $val) 
			{
				$val->node_route = str_replace($old_route, $new_route, $val->node_route);
				$val->node_route_text = $route_text;
				$output = executeQuery('svitem.updateCategoryInfo', $args);
			}
		}
		// update current
		$output = executeQuery('svitem.updateCategoryInfo', $args);
		if (!$output->toBool())
			return $output;
		// root folder has no node_id.
		$this->_updateSubItem($node_id, $old_route);
	}
/**
 * @brief 
 */
	private function _updateSubItem($node_id, $old_route)
	{
		// check node_id
		if (!$node_id && $old_route)
			return new BaseObject(-1, 'msg_invalid_request');

		// get node_route
		$args->node_id = $node_id;
		$output = executeQuery('svitem.getCategoryInfo', $args);
		if (!$output->toBool())
			return $output;
		$node_route = $output->data->node_route . $node_id . '.';

		// get subfolder count
		unset($args);
		$args->node_route = $old_route;
		$output = executeQuery('svitem.getItemsByNodeRoute', $args);
		if (!$output->toBool())
			return $output;
		// update subfolder count
		unset($args);

		foreach($output->data as $k => $v)
		{
			$args->item_srl = $v->item_srl;
			$args->node_route = $node_route;
			$output = executeQuery('svitem.updateItem', $args);
		}
		return $output;
	}
/**
 * @brief 
 */
	private function _moveNodeToNext($node_id, $parent_id, $next_id) 
	{
		$logged_info = Context::get('logged_info');
		if (!$logged_info) 
			return;

		$args->node_id = $next_id;
		$output = executeQuery('svitem.getCategoryInfo', $args);
		if (!$output->toBool()) 
			return $output;
		$next_node = $output->data;
		unset($args);

		// plus next siblings
		$args->node_route = $next_node->node_route;
		$args->list_order = $next_node->list_order;
		$output = executeQuery('svitem.updateCategoryOrder', $args);
		if (!$output->toBool())
			return $output;

		// update myself
		$list_order = $next_node->list_order;
		$args->node_id = $node_id;
		$args->list_order = $list_order;
		$output = executeQuery('svitem.updateCategoryNode', $args);
		if (!$output->toBool())
			return $output;
	}
/**
 * @brief 
 */
	private function _moveNodeToPrev($node_id, $parent_id, $prev_id)
	{
		$logged_info = Context::get('logged_info');
		if (!$logged_info)
			return;

		$args->node_id = $prev_id;
		$output = executeQuery('svitem.getCategoryInfo', $args);
		if (!$output->toBool())
			return $output;
		$prev_node = $output->data;
		unset($args);

		// update myself
		$list_order = $prev_node->list_order+1;
		$args->node_id = $node_id;
		$args->list_order = $list_order;
		$output = executeQuery('svitem.updateCategoryNode', $args);
		if (!$output->toBool())
			return $output;
	}
/**
 * @brief [상품목록] 메뉴에 jstree 1.3.3 node insert
 * jstree create UI 때문에 이 메소드가 호출될 때는 category_name을 입력받을 수 없음
 * create event 직후에 호출되는 jstree rename UI로 category_name 설정해야 함
 **/
	private function _insertCatalogNodeAjax()
	{
		$nModuleSrl = (int)Context::get('module_srl');
		$nParentCategoryNodeSrl = (int)Context::get('parent_srl');
		if(!$nModuleSrl || !strlen($nParentCategoryNodeSrl))
			return new BaseObject(-1, 'msg_invalid_request');

		if( $nParentCategoryNodeSrl != 0 ) // validate parent node
		{
			$oSvitemAdminModel = &getAdminModel('svitem');
			$oCatalog = $oSvitemAdminModel->getCatalog($nModuleSrl, $nParentCategoryNodeSrl);
			if(!$oCatalog->current_catalog_info)
				return new BaseObject(-1, 'msg_parent_node_not_found');
		}

		$nUniqueSrl = getNextSequence();
		$args->category_node_srl = $nUniqueSrl;
		$args->module_srl = $nModuleSrl;
		$args->parent_srl = $nParentCategoryNodeSrl;
		$args->category_name = 'please rename this '.$nUniqueSrl;
		$args->listorder = $nUniqueSrl;
		$output = executeQuery('svitem.insertAdminCategoryNode', $args);
		return $output;
		//$this->setMessage( 'success_inserted' );
	}
/**
 * @brief [상품목록] 메뉴에 jstree 1.3.3 node delete
 **/
	private function _deleteCatalogNodeAjax()
	{
		$nModuleSrl = (int)Context::get('module_srl');
		$nCategoryNodeSrl = (int)Context::get('category_node_srl');
		
		if(!$nModuleSrl || !$nCategoryNodeSrl)
			return new BaseObject(-1, 'msg_invalid_request');
			
		$oSvitemAdminModel = &getAdminModel('svitem');
		// retrieve current & children node information
		$oCatalog = $oSvitemAdminModel->getCatalog($nModuleSrl, $nCategoryNodeSrl);
		if($oCatalog->current_catalog_info->direct_belonged_item_cnt > 0 )
			return new BaseObject(-1, 'msg_items_exist_in_catalog');

		if($oCatalog->exists_children_catalog)
			return new BaseObject(-1, 'msg_subcategory_exist_in_category');

		if ($oCatalog->children_owned_item_count > 0 )
			return new BaseObject(-1, 'msg_items_exist_in_subcatalog');

		unset($args);
		$args->module_srl = (int)$nModuleSrl;
		$args->category_node_srl = (int)$nCategoryNodeSrl;
		$output = executeQuery('svitem.deleteCategory', $args);
		return $output;
		//$this->setMessage( 'success_deleted' );
	}
/**
 * @brief [상품목록] 메뉴에 jstree 1.3.3 node update
 **/
	private function _renameCatalogNodeAjax()
	{
		$sNodeName = trim(Context::get('node_name'));
		if( strlen( $sNodeName ) == 0 )
			return new BaseObject(-1, 'msg_node_name_required');
		$args->category_name = $sNodeName;
		$args->module_srl = (int)Context::get('module_srl');
		$args->category_node_srl = (int)Context::get('category_node_srl');
		$output = executeQuery('svitem.updateCategoryNode', $args);
		if (!$output->toBool())
		{
			$output->setMessage( 'error occured' );
		}
		return $output;
		//$this->setMessage( 'success_renamed' );
	}
/**
 * @brief [상품목록] 메뉴에 jstree 1.3.3 node update event 발생하면 ./files/cache/svitem/ 폴더 비우기
 **/
	private function _resetCache()
	{
		FileHandler::removeFilesInDir('./files/cache/svitem/');
	}
}
/* End of file svitem.admin.controller.php */
/* Location: ./modules/svitem/svitem.admin.controller.php */