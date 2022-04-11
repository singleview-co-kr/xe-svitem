(function($) {
	jQuery(function($) {
		// star point in itemdetail.html
		jQuery('ul.starPoint').find('a').click(function() {
			var o = jQuery(this);
			jQuery('ul.starPoint').find('a').each( function(i) {
				if(i<o.attr('rel')) jQuery(this).addClass('on');
				else jQuery(this).removeClass('on');
			});
			jQuery('input[name=star_point]').val(o.attr('rel'));
		});

		// declared in cartitems.html
		$('#deleteFavoriteItems').click(function() {
			var item_srls = new Array();
			$('input[name=favorite_cart]:checked').each(function() {
				item_srls[item_srls.length] = $(this).val();
			});
			var params = new Array();
			params['item_srls'] = item_srls.join(',');
			var responses = ['error','message'];
			exec_xml('svitem', 'procSvitemDeleteFavoriteItems', params, completeDeleteFavoriteItems, responses);
		});

		$('.iconUp').live('click', function() {
			var target = $(this).attr('data-for');
			var ival = parseInt($('#'+target).val());
			ival++;
			$('#'+target).val(ival);
		});
		$('.iconDown').live('click', function() {
			var target = $(this).attr('data-for');
			var ival = parseInt($('#'+target).val());
			ival--;
			if (ival < 1) ival = 1;
			$('#'+target).val(ival);
		});
		$('.updateQuantity').live('click', function() {
			var target = $(this).attr('data-for');
			var ival = parseInt($('#'+target).val());
			var params = new Array();
			params['cart_srl'] = target.replace(/[^0-9]/g,'');;
			params['quantity'] = ival;
			var responses = ['error','message'];
			exec_xml('svitem', 'procSvitemUpdateQuantity', params, completeDeleteFavoriteItems, responses);
		});
	});
}) (jQuery);

function number_format(nStr)
{
    nStr += '';
    x = nStr.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? '.' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ',' + '$2');
    }
    return x1 + x2;
}

function getRawPrice(price) {
	if (!g_decimals) return price;
	var multi = parseInt(Math.pow(10, g_decimals));
	var result = price * multi;
	return result.toFixed(0);
}

function getPrice(price) {
	if (!g_decimals) return price;
	var division = Math.pow(10, g_decimals);
	return price / division;
}

function getPrintablePrice(price) {
	var num = getPrice(price);
	return number_format(num.toFixed(g_decimals));
}

/**
 * make array to a string delimited with comma.
 */
function makeList() {
	var list = new Array();
	jQuery('input[name=cart]:checked').each(function(idx, elem) {
		list[list.length] = jQuery(elem).val();
	});
	return list;
}

function makeQuantityList() {
	var list = new Array();
	jQuery('input[name=cart]:checked').each(function(idx, elem) {
		list[list.length] = jQuery(elem).parent().parent().find('.quantity').val();
	});
	return list;
}
/**
 * callback function of procNstore_digitalDeleteFavoriteItems.
 */
function completeDeleteFavoriteItems(ret_obj)
{
	alert(ret_obj['message']);
	location.href = current_url;
}
/**
 * add items into cart
 */
function addItemsToCart(item_srl)
{
	var params = new Array();
	// if item_srl is not passed, throw item_srl list delimited with comma.
	if (typeof(item_srl)=='undefined')
	{
		params['item_srl'] = makeList();
		params['quantity'] = makeQuantityList();
	}
	else
	{
		var quantity = jQuery('#quantity_'+item_srl).val();
		params['item_srl'] = item_srl;
		if (quantity) params['quantity'] = quantity;

		var option_list = new Array();
		jQuery('input[name=option_srls]').each(function(idx, elem) {
			option_list[option_list.length] = jQuery(elem).val();
		});
		params['option_srls'] = option_list.join(',');

		if (option_list.length)
		{
			var quantity_list = new Array();
			jQuery('input[name=quantities]').each(function(idx, elem) {
				quantity_list[quantity_list.length] = jQuery(elem).val();
			});
			params['quantity'] = quantity_list.join(',');
		}
	}

	exec_xml('svitem', 'procSvitemAddItemsToCartObj', params, function(ret_obj) {
		recent_item_reload = jQuery("#c_recent_item").val();	
		if(recent_item_reload == 'true')
			r_load_cart('cart');

		if(jQuery("#is_mobile").val() == "true")
		{
			if (confirm('장바구니에 담겼습니다. 장바구니로 이동하시겠습니까?')) 
			{
				mid = jQuery("#svcart_mid").val();
				current_url = current_url.setQuery('document_srl', '');
				location.href = current_url.setQuery('mid', mid).setQuery('act','dispSvcartCartItems');
			}
		}
	});

	if(typeof loaded_count == 'function') loaded_count();
}
/**
 * add items into favorites
 */
function addItemsToFavorites(item_srl) 
{
	var params = new Array();
	// if item_srl is not passed, throw item_srl list delimited with comma.
	if (typeof(item_srl)=='undefined')
		params['item_srl'] = makeList();
	else
		params['item_srl'] = item_srl;
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
	if(typeof loaded_count == 'function')
		loaded_count();
}

function popup_modal(url, title, width, height)
{
	$dialog = jQuery('#modal-dialog');
	$dialog.dialog({title:title, width:width, height:height, modal:true, buttons:false, resizable:true});
	$dialog.html('<div class="loading-animation"></div>');
	var $iframe = jQuery('<iframe src="' + url + '" frameborder="0" style="border:0 none; width:100%; height:100%; padding:0; margin:0; background:transparent;"></iframe>');
	$iframe.ready(function() {
		setTimeout(function() { jQuery('#modal-dialog').html($iframe) }, 500);
	});
}

function close_modal() 
{
	jQuery('#modal-dialog').dialog('close');
}


function addDays(myDate, days) 
{
	return new Date(myDate.getTime() + days*24*60*60*1000);
}

function addMonth(currDate, month) 
{
	var currDay   = currDate.getDate();
	var currMonth = currDate.getMonth();
	var currYear  = currDate.getFullYear();
	var ModMonth = currMonth + month;
	if (ModMonth > 12) 
	{
		ModMonth = ModMonth - 12;
		currYear = currYear + 1;
	}
	if (ModMonth < 0) 
	{
		ModMonth = 12 + (ModMonth);
		currYear = currYear - 1;
	}
	return new Date(currYear, ModMonth, currDay);
}

function change_period(days, month)
{
	var currdate = new Date();
	if (days) 
		currdate = addDays(currdate, -1 * days);
	if (month) 
		currdate = addMonth(currdate, -1 * month);

	var startdate = jQuery.datepicker.formatDate('yymmdd', currdate);
	var startdateStr = jQuery.datepicker.formatDate('yy-mm-dd', currdate);
	jQuery('#orderlist .period input[name=startdate]').val(startdate);
	jQuery('#orderlist .period #startdateInput').val(startdateStr);
	jQuery('#fo_search').submit();
}