<?php
class catalogNode
{
	public $g_oParent=null; // Link to parent catalog
	public $g_nCatNodeSrl=-1;
	public $g_sCatName='';
	public $g_nBelongedItemCnt=0;
	public $g_aoChildren=array(); // Link to children catalog

	public function __construct($oArgs)
	{
		if( is_null( $oArgs->node_srl ) )
			return false;

		if( is_null( $oArgs->catalog_name ) )
			$oArgs->catalog_name = 'temp';

		if( isset( $oArgs->parent_node ) )
			$this->g_oParent=$oArgs->parent_node;

		$this->g_nCatNodeSrl=$oArgs->node_srl;
		$this->g_sCatName=$oArgs->catalog_name;
		$this->g_nBelongedItemCnt=$oArgs->belonged_item_count;
		$this->g_aoChildren = array();
	}
	public function readNode()
	{
		$oRet = new stdClass();
		$oRet->node_srl = $this->g_nCatNodeSrl;
		$oRet->catalog_name = $this->g_sCatName;
		return $oRet;
	}
}
/**
 * @class  svitemCatalog
 * @author singleview(root@singleview.co.kr)
 * @brief 부모 노드는 반드시 자식 노드보다 먼저 추가되어야 함
 * 부모 노드가 자식 노드보다 늦게 추가되는 경우의 처리를 추가해야 함
 */
class catalogList
{
	private static $_g_nIterationCnt=0;
	private static $_g_nChildBelongItemCnt=0;
	private $_g_nTotalNodes=0;
	private $_g_aChildSrls=array();
	private $_g_oRoot=null;
	private $_g_oFoundNode=null;

	public function __construct()
	{
		$oNodeInfo->node_srl=0;
		$oNodeInfo->catalog_name='root';
		$oNodeInfo->belonged_item_count=0;
		$this->_g_oRoot = new catalogNode($oNodeInfo);
		$this->_g_nTotalNodes = 0;
	}
	public function isEmpty()
	{
		return ($this->_g_nTotalNodes == 0);
	}
	public function getNodeCount()
	{
		return $this->_g_nTotalNodes;
	}
	public function insertNode($oNodeInfo)
	{
		$this->_findNode($this->_g_oRoot, $oNodeInfo->node_srl); // 현재 노드가 존재하는지 확인
		if( !$this->_g_oFoundNode ) // 현재 노드가 없다면
		{
			// 현재 노드의 부모 노드가 존재하는지 확인
			$this->_findNode($this->_g_oRoot, $oNodeInfo->parent_srl);
			if( $this->_g_oFoundNode ) // 현재 노드의 부모 노드가 있다면
			{
				$oNodeInfo->parent_node = $this->_g_oFoundNode; // 현재 노드의 부모 노드에 현재 노드의 부모 노드를 붙임
				$oCatalog = new catalogNode($oNodeInfo); // 현재 노드를 생성 
				array_push($this->_g_oFoundNode->g_aoChildren, $oCatalog); // 현재 노드의 부모 밑에 현재 노드를 붙임
				unset( $this->_g_oFoundNode );
				$this->_g_nTotalNodes++;
				return true;
			}
			else // 현재 노드의 부모 노드가 없다면, 사후 추가되는 부모노드의 완전한 정보를 반영하는 코드 필요함
			{
				$oParentNodeInfo->parent_node = $this->_g_oRoot; // 현재 노드의 부모 노드의 부모 노드에 루토를 붙임
				$oParentNodeInfo->node_srl=$oNodeInfo->parent_srl; // 현재 노드의 부모 노드를 생성
				$oParentNodeInfo->catalog_name='temp';
				$oParentNodeInfo->belonged_item_count=0;
				$oTempParentCatalog = new catalogNode($oParentNodeInfo);
				array_push($this->_g_oRoot->g_aoChildren, $oTempParentCatalog); // 현재 노드의 부모를 root에  붙임
				$oNodeInfo->parent_node = $oTempParentCatalog; // 현재 노드의 부모 노드에 현재 노드의 부모 노드를 붙임
				$oCatalog = new catalogNode($oNodeInfo); // 현재 노드를 생성 
				array_push($oTempParentCatalog->g_aoChildren, $oCatalog); // 현재 노드의 부모 밑에 현재 노드를 붙임
				$this->_g_nTotalNodes+=2;
				return true;
			}
		}
		else // 현재 노드가 있다면 -> 사후 추가되는 부모노드의 완전한 정보를 반영하는 코드 필요함
			return false;
	}
	public function getNodeInfo($nNodeSrlToFind)
	{
		$bRst = $this->_findNode($this->_g_oRoot, $nNodeSrlToFind);
		if( $bRst )
		{
			$oCurNodeRst->node_srl = $this->_g_oFoundNode->g_nCatNodeSrl;
			$oCurNodeRst->node_name = $this->_g_oFoundNode->g_sCatName;
			$oCurNodeRst->direct_belonged_item_cnt = $this->_g_oFoundNode->g_nBelongedItemCnt;
			$oRst = new stdClass();
			$oRst->parent_node_info = $this->_getParentInfo();
			$oRst->cur_node_info = $oCurNodeRst;
			$oRst->direct_child_node_info = $this->_gettDirectChildInfo();
			return $oRst;
		}
		else
			return false;
	}
	private function _getParentInfo()
	{
		$_aParentNode = array();
		$_oTempNode = $this->_g_oFoundNode->g_oParent;
		while( $_oTempNode != null )
		{
			$_oCurNodeInfo = new stdClass();
			$_oCurNodeInfo->node_srl = $_oTempNode->g_nCatNodeSrl;
			$_oCurNodeInfo->node_name = $_oTempNode->g_sCatName;
			$_aParentNode[] = $_oCurNodeInfo;
			$_oTempNode = $_oTempNode->g_oParent;
		}
		$_aParentNode = array_reverse($_aParentNode);
		return $_aParentNode;
	}
	private function _gettDirectChildInfo()
	{
		$_aDirectChildrenNode = array();
		foreach( $this->_g_oFoundNode->g_aoChildren as $key=>$val )
		{
			$this->_g_aChildSrls = array();
			$this->_g_aChildSrls[] = $val->g_nCatNodeSrl;

			$_oCurNodeInfo = new stdClass();
			$_oCurNodeInfo->cur_node_srl = $val->g_nCatNodeSrl;
			$_oCurNodeInfo->cur_node_name = $val->g_sCatName;
			
			$this->_getChildItemInfo($val);
			$_oCurNodeInfo->total_belonged_item_cnt = $val->g_nBelongedItemCnt + $this->_g_nChildBelongItemCnt;
			$_oCurNodeInfo->all_children_srls = $this->_g_aChildSrls;
			$_aDirectChildrenNode[] = $_oCurNodeInfo;
		}
		return $_aDirectChildrenNode;
	}
	private function _getChildItemInfo($oStartNode)
	{
		$this->_g_nIterationCnt = 0;
		$this->_g_nChildBelongItemCnt = 0;
		$this->_iterateRecursiveChildrenBelongedItemCnt($oStartNode);
	}
	private function _iterateRecursiveChildrenBelongedItemCnt($oStartNode)
	{
		foreach( $oStartNode->g_aoChildren as $key=>$val)
		{
			$this->_g_nChildBelongItemCnt += $val->g_nBelongedItemCnt;
			$this->_g_aChildSrls[] = $val->g_nCatNodeSrl;
//echo $val->g_sCatName.'->'.$val->g_nCatNodeSrl.'<BR>';
//echo $this->_g_nChildBelongItemCnt.'<BR>';
			if( count($val->g_aoChildren) > 0 )
				$this->_iterateRecursiveChildrenBelongedItemCnt($val);
		}
	}
	private function _findNode($oStartNode, $nNodeSrlToFind)
	{
		$this->_g_oFoundNode = null;
		$this->_g_nIterationCnt = 0;
		$this->_iterateRecursionFind($oStartNode, $nNodeSrlToFind);
		if( is_null( $this->_g_oFoundNode ) )
			return false;
		else
			return true;
		
	}
	private function _iterateRecursionFind($oStartNode, $nNodeSrlToFind)
	{
		$_oCurNode = $oStartNode;
//echo '<BR>nNodeSrlToFind:'.$nNodeSrlToFind.'curNodeSrl:'.$_oCurNode->g_nCatNodeSrl.'<BR>';
		if($_oCurNode->g_nCatNodeSrl == $nNodeSrlToFind )
		{
//echo '<BR>matched<BR>';
			$this->_g_oFoundNode = $_oCurNode;
			return true;
		}
		if( isset( $_oCurNode->g_aoChildren ) )
		{
			foreach( $_oCurNode->g_aoChildren as $key=>$val )
			{
				if($val == NULL)
					return false;
				else
					$this->_iterateRecursionFind($val, $nNodeSrlToFind);
			}
		}
		else
			return false;

		if( $this->_g_nIterationCnt > 100 ) // sentinel to prevent inifite loop
			return false;
		else
			$this->_g_nIterationCnt++;
//echo '<BR>iteration:'.$this->_g_nIterationCnt.'<BR>';
	}
}