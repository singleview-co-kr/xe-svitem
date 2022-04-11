var _g_nFbLikedPromtionAmnt = 0;
var _g_bFB_liked = 0;
var _g_nFbSharedPromtionAmnt = 0;
var _g_bFB_shared = 0;
var _g_bFB_liked_normal_price_changed = 0;
var _g_bFB_shared_normal_price_changed = 0;
var _g_bExtmallLayerProcessed = false;

jQuery(function($) {
	$('#select_options').change(function() 
	{
		var option_srl = $(this).val();
		if (!option_srl)
			return;
		var $opt = $('option:selected',this);
		var title = $opt.attr('data-title');
		var price = parseFloat($opt.attr('data-price'));
		var str_price='';
		if (price > 0) 
			str_price = '(' + '+' + number_format(price) + ')';
		if (price < 0) 
			str_price = '(' + number_format(price) + ')';

		if (!$('#option_'+option_srl).length) {
			$('#selected_options').append('<tr id="option_'+option_srl+'" data-price="'+ (g_discounted_price + (price)) +'"><td>'+ title + str_price + '</td><td><input type="hidden" name="option_srls" value="' + option_srl + '" /><input type="text" name="quantities" class="quantity" value="1" />' + xe.lang.each + '</td><td><span onclick="jQuery(this).parent().parent().remove(); printTotalPrice();" class="deleteItem">X</span></td><td><span>' + number_format(g_discounted_price + (price)) + '</span></td></tr>');
		}

		printTotalPrice();
	});
	$('#selected_options input').live('change', function() {
		printTotalPrice();
	});

	calculate_sum();
	jQuery('input[name=related_item]').change(function() {
		calculate_sum();
	});
});

function drawPriceTag( nNormalPrice, nDiscountAmnt, nDiscountedPrice )
{
	nNormalPrice = parseInt( nNormalPrice );
	nDiscountAmnt = parseInt( nDiscountAmnt );
	nDiscountedPrice = parseInt( nDiscountedPrice );
	
	jQuery('#sales_price').html( number_format(parseInt( nNormalPrice ) ) ); 

	if( nDiscountAmnt > 0 )
	{
		jQuery('#normal_price_tag').wrap('<strike>');
		jQuery('#discounted_price').html( number_format(parseInt( nDiscountedPrice ) ) ); 
		jQuery('#discounted_price_tag').show();
	}
}

function getTotalPrice() {
	var total_amount = 0;
	// 제품 구매 옵션이 출력되었을 때
	if( jQuery('#selected_options tr').length )
	{
		jQuery('#selected_options tr').each(function() {
			if(jQuery('.quantity', this).val() < 1)
			{
				alert(xe.lang.msg_input_more_than_one); 
				jQuery('.quantity').val('1');
				var quantity = 1;
			}
			else 
				var quantity = jQuery('.quantity', this).val();

			var price = parseFloat(jQuery(this).attr('data-price'));
			total_amount += ((price) * quantity);
		});
	}
	else // 제품 구매 옵션이 없을 때
	{
		var sSalesPrice = jQuery('#sales_price').html();
		sSalesPrice = sSalesPrice.replace(/,/g, '');
		var nSalesPrice = parseInt( sSalesPrice );
		
		if( _g_bFB_liked && !_g_bFB_liked_normal_price_changed )
			total_amount = nSalesPrice -_g_nFbLikedPromtionAmnt;
		if( _g_bFB_shared && !_g_bFB_shared_normal_price_changed )
			total_amount = nSalesPrice -_g_nFbSharedPromtionAmnt;
	}
	return total_amount;
}

function applyFbSharePromotion()
{
	_g_bFB_shared = 1;
	if( _g_nFbSharedPromtionAmnt == 0 )
	{
		var sSalesPrice = jQuery('#sales_price').html();
		sSalesPrice = sSalesPrice.replace(/,/g, '');
		var nSalesPrice = parseInt( sSalesPrice );
		// svpromotion에서 FB LIKE 할인액 가져오기
		var params = new Array();
		// params['item_srl'] = item_srl;
		params['item_srl'] = g_nItemSrl;
		params['promotion_type'] = 'fbshare';
		var respons = ['nConditionalPromoAmnt'];
		exec_xml('svitem', 'getSvitemConditionalPromoInfo', params, function(ret_obj) {
			_g_nFbSharedPromtionAmnt = parseInt( ret_obj['nConditionalPromoAmnt'] );
			g_discounted_price = nSalesPrice - _g_nFbSharedPromtionAmnt;
			if( jQuery('#selected_options').html() )
				jQuery('#selected_options').html('');

			printTotalPrice();
		},respons);
	}
}

function applyFbLikePromotion()
{
	_g_bFB_liked = 1;

	if( _g_nFbLikedPromtionAmnt == 0 )
	{
		var sSalesPrice = jQuery('#sales_price').html();
		sSalesPrice = sSalesPrice.replace(/,/g, '');
		var nSalesPrice = parseInt( sSalesPrice );
		// svpromotion에서 FB LIKE 할인액 가져오기
		var params = new Array();
		// params['item_srl'] = item_srl;
		params['item_srl'] = g_nItemSrl;
		params['promotion_type'] = 'fblike';
		var respons = ['nConditionalPromoAmnt'];
		exec_xml('svitem', 'getSvitemConditionalPromoInfo', params, function(ret_obj) {
			_g_nFbLikedPromtionAmnt = parseInt( ret_obj['nConditionalPromoAmnt'] );
			g_discounted_price = nSalesPrice - _g_nFbLikedPromtionAmnt;
			if( jQuery('#selected_options').html() )
				jQuery('#selected_options').html('');

			printTotalPrice();
		},respons);
	}
}

function printTotalPrice() {
	var total_price = getTotalPrice();
	jQuery('#total_amount')
		.html('<span>' + xe.lang.total_amount + ': <span class="red">'+ number_format(total_price) +'</span></span>')
		.attr('data-amount', total_price);
	calculate_sum();
}

function float2int( value )
{
    return value | 0;
}

function calculate_sum() 
{
	var related_sum = g_discounted_price;
	var total_amount = parseInt(jQuery('#total_amount').attr('data-amount'));

	if(total_amount > 0) 
		related_sum = total_amount;
	
	var sFbLikePromotion = '';
	if( _g_bFB_liked == 1 )
	{
		var sDicountInfo = jQuery('#discount_info').html();
		if( sDicountInfo.search('좋아해') == -1 )
		   sFbLikePromotion = "<span style='font-size:12px; color:red;'>좋아해 주셔서 감사드려요!</span>";
	}

	var sFbSharePromotion = '';
	if( _g_bFB_shared == 1 )
	{
		var sDicountInfo = jQuery('#discount_info').html();
		if( sDicountInfo.search('공유해') == -1 )
		   sFbSharePromotion = "<span style='font-size:12px; color:red;'>공유해 주셔서 감사드려요!</span>";
	}

	jQuery('input[name=related_item]:checked').each(function(idx, elm) {
		var price = parseInt(jQuery(elm).attr('data-price'));
		related_sum += price;
	});
	if( jQuery('#related_sum').length) // 구매 옵션이 표시되었을 때
	{
		jQuery('#related_sum').html(number_format(related_sum ));
		if( _g_bFB_liked == 1 && !_g_bFB_liked_normal_price_changed ) // 페북 라이크 할인이 없으면, 가격 변경하지 않음
		{
			jQuery('#normal_price_tag').wrap('<strike>');
			jQuery('#discounted_price').html(number_format(g_discounted_price));
			
			var sPromotionTitle = '';
			if( jQuery('#discounted_price_tag').is(':visible') )
				sPromotionTitle = jQuery('#discount_info').html() + ' ' + sFbLikePromotion;
			else
				sPromotionTitle = sFbLikePromotion;
			
			jQuery('#discount_info').html( sPromotionTitle );
			jQuery('#discounted_price_tag').show();
			_g_bFB_liked_normal_price_changed++; // 재실행 방지
		}
		if( _g_bFB_shared == 1 && !_g_bFB_shared_normal_price_changed ) // 페북 라이크 할인이 없으면, 가격 변경하지 않음
		{
			jQuery('#normal_price_tag').wrap('<strike>');
			jQuery('#discounted_price').html(number_format(g_discounted_price));
			
			var sPromotionTitle = '';
			if( jQuery('#discounted_price_tag').is(':visible') )
				sPromotionTitle = jQuery('#discount_info').html() + ' ' + sFbSharePromotion;
			else
				sPromotionTitle = sFbSharePromotion;
			
			jQuery('#discount_info').html( sPromotionTitle );
			jQuery('#discounted_price_tag').show();
			_g_bFB_shared_normal_price_changed++; // 재실행 방지
		}
	}
	else // 구매 옵션이 없을 때
	{
		if( _g_bFB_liked == 1 ) // 페북 라이크 할인이 없으면, 가격 변경하지 않음
		{
			jQuery('#normal_price_tag').wrap('<strike>');
			jQuery('#discounted_price').html(number_format(related_sum));
			
			var sPromotionTitle = '';
			if( jQuery('#discounted_price_tag').is(':visible') )
				sPromotionTitle = jQuery('#discount_info').html() + ' ' + sFbLikePromotion;
			else
				sPromotionTitle = sFbLikePromotion;
			
			jQuery('#discount_info').html( sPromotionTitle );
			jQuery('#discounted_price_tag').show();
		}
		if( _g_bFB_shared == 1 ) // 페북 공유 할인이 없으면, 가격 변경하지 않음
		{
			jQuery('#normal_price_tag').wrap('<strike>');
			jQuery('#discounted_price').html(number_format(related_sum));
			var sPromotionTitle = '';
			if( jQuery('#discounted_price_tag').is(':visible') )
				sPromotionTitle = jQuery('#discount_info').html() + ' ' + sFbSharePromotion;
			else
				sPromotionTitle = sFbSharePromotion;
			
			jQuery('#discount_info').html( sPromotionTitle );
			jQuery('#discounted_price_tag').show();
		}
	}
}
////////////////////////////////////////////
/**
 * add items into favorites
 */
function addItemsToFavoritesInDetailPage()
{
	var params = new Array();
	params['item_srl'] = g_nItemSrl;
	
	exec_xml('svitem', 'procSvitemAddItemsToFavorites', params, function(ret_obj) {
		recent_item_reload = jQuery("#c_recent_item").val();	
		if(recent_item_reload == 'true')
			r_load_favorites('wish');

		if(jQuery("#is_mobile").val() == "true")
		{
			if (confirm('관심상품에 추가하였습니다. 관심상품으로 이동하시겠습니까?')) 
			{
				mid = jQuery("#Svcart_mid").val();
				current_url = current_url.setQuery('document_srl', '');
				location.href = current_url.setQuery('mid', mid).setQuery('act','dispSvcartFavoriteItems');
			}
		}
	});
}
//////////////////////////////////////////////
/**
 * add items into cart
 */
function addItemsToCartInDetailPage()
{
	var params = new Array();
	params['item_srl'] = g_nItemSrl;
	var respons = ['sExtmallLoggerToggle','sLayerHtml'];
	exec_xml('svitem', 'getSvitemDetailPageAction', params, function(ret_obj) {
		sExtmallToggle = ret_obj['sExtmallLoggerToggle'];
		if( sExtmallToggle == 'on' )
		{
			_showExtmallLayerPopup(ret_obj['sLayerHtml']);
			gatkDetail.patchBuyImmediately( 1 );
		}
		else
		{
			// var param = _getCartParamsInDetailPage(item_srl);
			var param = _getCartParamsInDetailPage();
// Enhanced E-Commerce Tagging Begin (20151121) singleview.co.kr
			var nParamCnt = param.length;
			var nQty = 0;
			for( var i = 0; i < nParamCnt; i++ )
				nQty += parseInt( param[i].quantity );

			gatkDetail.patchAddToCart( nQty );
// Enhanced E-Commerce Tagging End (20151121) singleview.co.kr
			_addItemsToCartObj(param);
		}
	},respons);
}
/**
 * direct order
 */
function _showExtmallLayerPopup(sLayerHtml)
{
	if( !_g_bExtmallLayerProcessed ) // prevent duplicated load
	{
		// load layer popup html
		jQuery('body').append(sLayerHtml);
		// load css
		var isMobile = false; //initiate as false
		// device detection
		if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent) 
			|| /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0,4))) 
			isMobile = true;

		var sCssFullPath = isMobile ? '/modules/svitem/tpl/css/layer_popup_mob.css' : '/modules/svitem/tpl/css/layer_popup_pc.css'

		if( document.createStyleSheet )
			document.createStyleSheet(sCssFullPath);
		else 
			jQuery("head").append(jQuery("<link rel='stylesheet' href='"+ sCssFullPath + "' type='text/css' media='screen' />"));
		
		jQuery.getScript("/modules/svitem/tpl/skin.js/layer_popup.js", function(){}); // load javascript
		
		_g_bExtmallLayerProcessed = true;

		var delayInMilliseconds = 300; // wait for completing layer DOM element
		setTimeout(function() {
			layer_open('layer_ext_mall');
			var scmove = jQuery('#layer_ext_mall').offset().top;
			jQuery('html, body').animate( { scrollTop : scmove }, 400 );
		}, delayInMilliseconds);
	}
	else
	{
		layer_open('layer_ext_mall');
		var scmove = jQuery('#layer_ext_mall').offset().top;
		jQuery('html, body').animate( { scrollTop : scmove }, 400 );
	}
}
/**
 * npay order
 */
function onClickNaverpayBuy( sUrl ) 
{
	var params = new Array();
	params['item_srl'] = g_nItemSrl;
	var respons = ['sExtmallLoggerToggle','sLayerHtml'];
	exec_xml('svitem', 'getSvitemDetailPageAction', params, function(ret_obj) {
		sExtmallToggle = ret_obj['sExtmallLoggerToggle'];
		if( sExtmallToggle == 'on' )
		{
			_showExtmallLayerPopup(ret_obj['sLayerHtml']);
			gatkDetail.patchBuyImmediately( 1 );
		}
		else
		{
			var param = _getCartParamsInDetailPage();
// Enhanced E-Commerce Tagging Begin (20151121) singleview.co.kr
			var nParamCnt = param.length;
			var nQty = 0;
			for( var i = 0; i < nParamCnt; i++ )
				nQty += parseInt( param[i].quantity );

			gatkDetail.patchBuyImmediately( nQty );
// Enhanced E-Commerce Tagging End (20151121) singleview.co.kr
			_addItemsToCartObjAndTransferToNpay(param);
		}
	},respons);
}
/**
 * add items into cart (new)
 * @param args format [ {item_srl:x, option_srl:x, quantity:x} , {item_srl:x, option_srl:x, quantity:x},...  ]
 *        item_srl is required.
 */
function _addItemsToCartObjAndTransferToNpay(args)
{
	if (typeof(args)=='undefined')
		return;
	if (!jQuery.isArray(args))
		args = [args];

	var params = new Array();
	nStock = 0;
	//var respons = ['nStockAvailable'];
	params['item_srl'] = g_nItemSrl;
	params['mode'] = 'npay'; // naver pay
	params['data'] = JSON.stringify(args);
	params['svcart_mid'] = g_sSvcartMid;//svcart_mid;
	var response_tags = new Array('error','message','cart_srl','nStockAvailable');
	exec_xml('svitem', 'procSvitemNaverpay', params, function(ret_obj) 
	{
		var nStockFromServer = ret_obj['nStockAvailable'];
		var oParam = _getCartParamsInDetailPage();
		console.log( oParam );
		// Enhanced E-Commerce Tagging Begin (20151121) singleview.co.kr
		var nParamCnt = oParam.length;
		var nOrderedQty = 0;
		for( var i = 0; i < nParamCnt; i++ )
			nOrderedQty += parseInt( oParam[i].quantity );
		
		if( nOrderedQty > nStockFromServer )
		{
			window.alert('재고가 부족합니다.');
			return;
		}

		if (confirm('네이버페이로 이동하시겠습니까?')) 
		{
			var sCartSrl = ret_obj['cart_srl'];
			current_url = current_url.setQuery('document_srl', '');
			location.href = current_url.setQuery('mid', g_sSvcartMid).setQuery('act','dispSvcartNpayItems').setQuery('cartnos',sCartSrl);
		}
	}, response_tags);
}
/**
 * direct order
 */
function orderItemsInDetailPage()
{
	var params = new Array();
	// params['item_srl'] = item_srl;
	params['item_srl'] = g_nItemSrl;
	var respons = ['sExtmallLoggerToggle','sLayerHtml'];
	exec_xml('svitem', 'getSvitemDetailPageAction', params, function(ret_obj) {
		sExtmallToggle = ret_obj['sExtmallLoggerToggle'];
		if( sExtmallToggle == 'on' )
		{
			_showExtmallLayerPopup(ret_obj['sLayerHtml']);
			gatkDetail.patchBuyImmediately( 1 );
		}
		else
		{
			// var param = _getCartParamsInDetailPage(item_srl);
			var param = _getCartParamsInDetailPage();
// Enhanced E-Commerce Tagging Begin (20151121) singleview.co.kr
			var nParamCnt = param.length;
			var nQty = 0;
			for( var i = 0; i < nParamCnt; i++ )
				nQty += parseInt( param[i].quantity );

			gatkDetail.patchBuyImmediately( nQty );
// Enhanced E-Commerce Tagging End (20151121) singleview.co.kr
			_orderItemsDirectly(param);
		}
	},respons);
}

/**
 * return cart parameter formatted string.
 * format [ {item_srl:x, option_srl:x, quantity:x} , {item_srl:x, option_srl:x, quantity:x},...  ]
 */
function _getCartParamsInDetailPage()
{
	var param = new Array();
	// validate bundling item composition
	var oCompiledFinalBundleItems = '';
	if( oRelatedProduct !== undefined && oRelatedProduct !== null )
	{
		if( oRelatedProduct.length )
		{
			var $oSelectedBundleItems;
			var $nChoosedQty = 0;
			var $nBundleQty;
			
			// validate ordered quantities
			for( x in oBundleQtyTable )
			{
//console.log( 'x:'+x );
				$nChoosedQty = 0;
				$oSelectedBundleItems = jQuery('#selected_bundles tr[data-typeid='+x+']');
//console.log( $oSelectedBundleItems);
				if( $oSelectedBundleItems.length == 0 )
				{
					alert(xe.lang.msg_no_bundling_item_selected);
					return;
				}
				else if( $oSelectedBundleItems.length > 0 )
				{
					var $oChoosedQty = $oSelectedBundleItems.find(':text');
					$oChoosedQty.each(function() {
						$nChoosedQty += parseInt( jQuery( this).val()   );
//console.log( '$nChoosedQty:' + jQuery( this).val()  );
					});

					//$nBundleQty = parseInt( oBundleQtyTable[x] ) * parseInt( jQuery('#item_'+item_srl+' :text' ).val() );
					$nBundleQty = parseInt( oBundleQtyTable[x] ) * parseInt( jQuery('#item_'+g_nItemSrl+' :text' ).val() );
					if( $nChoosedQty != $nBundleQty )
					{
//console.log( $nChoosedQty );
//console.log( $nBundleQty );
						alert(xe.lang.msg_bundling_item_quantity_not_match);
						return;
					}
				}
			}

			$oSelectedBundleItems = jQuery('#selected_bundles tr');
			$oSelectedBundleItems.each( function(){
	//			console.log( jQuery( this ).find(':text').val() );
	//			console.log( jQuery( this ).find(':hidden').val() );
				oCompiledFinalBundleItems += jQuery( this ).find(':hidden').val() + '^' + jQuery( this ).find(':text').val() + ',';
			});
		}
	}
	else
	{
		$oSelectedBundleItems = jQuery('#selected_bundles tr');
		$oSelectedBundleItems.each( function(){
//			console.log( jQuery( this ).find(':text').val() );
//			console.log( jQuery( this ).find(':hidden').val() );
			oCompiledFinalBundleItems += jQuery( this ).find(':hidden').val() + '^' + jQuery( this ).find(':text').val() + ',';
//console.log( oCompiledFinalBundleItems );
		});
	}

	var options_count = 0;
	jQuery('input[name=option_srls]').each(function(idx, elem) {
		var option_srl = jQuery(elem).val();
		var item = new Object();
		// item.item_srl = item_srl;
		item.item_srl = g_nItemSrl; 
		item.option_srl = option_srl;
		item.quantity = jQuery(elem).next('.quantity').val();
		item.fb_liked = _g_bFB_liked;
		item.fb_shared = _g_bFB_shared;
		
		param[param.length] = item;
		options_count++;
	});

	if (options_count == 0) {
		var item = new Object();
		// item.item_srl = item_srl;
		// item.quantity = jQuery('#quantity_'+item_srl).val();
		item.item_srl = g_nItemSrl;
		item.quantity = jQuery('#quantity_'+g_nItemSrl).val();
		item.fb_liked = _g_bFB_liked;
		item.fb_shared = _g_bFB_shared;
		
		if( $oSelectedBundleItems !== undefined && $oSelectedBundleItems !== null)
		{
			if( oCompiledFinalBundleItems.length > 0 )
				item.bundling_items = oCompiledFinalBundleItems;
		}
		param[param.length] = item;
	}
//console.log( oCompiledFinalBundleItems  );
//return;

	jQuery('input[name=related_item]:checked').each(function(idx, elem) {
		var item = new Object();
		item.item_srl = jQuery(elem).val();
		param[param.length] = item;
	});
	
//console.log( param);
	return param;
}

function _orderItemsDirectly(args)
{
	if( typeof(args)=='undefined' )
		return;

	if( !jQuery.isArray(args) )
		args = [args];

	var params = new Array();
	params['mode'] = 'bi'; // buy immediately
	params['data'] = JSON.stringify(args);
	params['svcart_mid'] = g_sSvcartMid;//svcart_mid;

	var response_tags = new Array('error','message','cart_srl','svorder_mid');
	exec_xml('svitem', 'procSvitemAddItemsToCartObj', params, function(ret_obj) 
	{
		var cart_srl = ret_obj['cart_srl'];
		var sSvorderMid = ret_obj['svorder_mid'];

		current_url = current_url.setQuery('document_srl', '');
		if( typeof(g_sSvcartMid) == 'undefined' || g_sSvcartMid == '' )
		{
			alert('SVCART MID is not defined.');
			return;
		}
		if( sSvorderMid )
			location.href = current_url.setQuery('mid', sSvorderMid).setQuery('act','dispSvorderOrderForm').setQuery('cartnos',cart_srl);
		else
		{
			alert('SVORDER MID is not defined.');
			return;
			//location.href = current_url.setQuery('mid', g_sSvcartMid).setQuery('act','dispSvcartOrderItems').setQuery('cartnos',cart_srl);
		}
	}, response_tags);
}

/**
 * add items into cart (new)
 * @param args format [ {item_srl:x, option_srl:x, quantity:x} , {item_srl:x, option_srl:x, quantity:x},...  ]
 *        item_srl is required.
 */
function _addItemsToCartObj(args)
{
	if (typeof(args)=='undefined')
		return;
	if (!jQuery.isArray(args))
		args = [args];

	var params = new Array();
	params['mode'] = 'atc'; // add to cart
	params['data'] = JSON.stringify(args);
	params['svcart_mid'] = g_sSvcartMid;//svcart_mid;
	var response_tags = new Array('error','message','go_to_cart');
	exec_xml('svitem', 'procSvitemAddItemsToCartObj', params, function(ret_obj) 
	{
		var bGoCart = ret_obj['go_to_cart'];
		if(bGoCart == 'Y')
		{
			if (confirm('장바구니에 담겼습니다. 장바구니로 이동하시겠습니까?')) 
			{
				//mid = g_sSvcartMid;//jQuery("#svcart_mid").val();
				current_url = current_url.setQuery('document_srl', '');
				//location.href = current_url.setQuery('mid', mid).setQuery('act','dispSvcartCartItems');
				location.href = current_url.setQuery('mid', g_sSvcartMid).setQuery('act','dispSvcartCartItems');
			}
		}
	}, response_tags);
}