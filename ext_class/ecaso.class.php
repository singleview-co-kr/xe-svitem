<?php
/**
 * @class  ecaso
 * @author singleview(root@singleview.co.kr)
 * @brief  ecaso stock query
 */
class ecaso
{
	var $_g_nStock=0;
/**
 * @brief 이카소 물류서버에서 재고 정보 추출
 */
	public function ecaso( $sItemCode ) 
	{
		if( !ini_get('allow_url_fopen') )
			return;

		if( strlen($sItemCode)>0)
			$oItemCode=explode('|',$sItemCode);
		else
		{
			$this->_g_nStock=0;
			return;
		}
		
		$nItemCodeCnt = count( $oItemCode );
		switch( $nItemCodeCnt )
		{
			case 1: // item without option
				$data = array('puid' => $oItemCode[0]);
				break;
			case 2: // item with option
				$data = array('puid' => $oItemCode[0], 'opuid' => $oItemCode[1]);
				break;
		}

		$url = 'http://211.115.91.247/api/balance_pr_num.php';
		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$sResult = file_get_contents($url, false, $context);
		if( $sResult === FALSE )
		{
			$this->_g_nStock=0;
			return;
		}
		else // a valid return consisted of nStockQty | nPuid {| nOpuid}
		{
			$oResult=explode('|',$sResult);
			switch( $nItemCodeCnt )
			{
				case 1: // item without option
					if( trim( $oResult[1] ) == trim( $oItemCode[0] ) )
					{
						$this->_g_nStock=(int)$oResult[0];
						return;
					}
					else
					{
						$this->_g_nStock=0;
						return;
					}
					break;
				case 2: // item with option
					if( trim( $oResult[1] ) == trim( $oItemCode[0] ) && trim( $oResult[2] ) == trim( $oItemCode[1] ) )
					{
						$this->_g_nStock=(int)$oResult[0];
						return;
					}
					else
					{
						$this->_g_nStock=0;
						return;
					}
					break;
			}
		}
		$this->_g_nStock=0;
		return;
	}
/**
 * @brief 추출한 정보를 전달
 */
	public function getStock()
	{
		return $this->_g_nStock;
	}
}
/* End of file ecaso.class.php */
/* Location: ./modules/svitem/ecaso.class.php */