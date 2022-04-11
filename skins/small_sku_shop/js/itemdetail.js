/*
 * jQuery inbody store item detail
 * Copyright 2014 singleview.co.kr
 * Contributing Author: aztr0v0iz
 */

$(document).ready(function()
{
	if( $('#item_name').html() == '인바디다이얼' )
		$('.apps-field p').show() 
	else
		$('.apps-field ul').show() 

	if( $('#item_name').html() == '인바디다이얼' )
		$('#item_name').html('인바디다이얼 H20')
	else if( $('#item_name').html() == '인바디다이얼 블루투스' )
		$('#item_name').html('인바디다이얼 블루투스 H20B')

	/**/
	// thumbnail-gallery
	var gals = $('.thumbnail-gallery .thumbnail-gal'),
	single_gals_num = $('.thumbnail-gallery:first .thumbnail-gal').length,
	gals_show_num = 5;
	
	if(single_gals_num > gals_show_num)
	{
		var signle_height = gals.outerHeight()+parseInt(gals.css('margin-top'))+parseInt(gals.css('margin-bottom'))+parseInt(gals.css('padding-top'))+parseInt(gals.css('padding-bottom'));
		var gal_wrap = $('.thumbnail-gallery-wrap');
		var step = 0;
		gal_wrap.css('height',signle_height*gals_show_num);
		$('.thumbnail-gallery-wrap-ctrl').append($('<i class="disabled"></i><b></b>'));
		$('.thumbnail-gallery-wrap-ctrl b').click(function()
		{
			if(step < single_gals_num-gals_show_num)
			{
				$('.thumbnail-gallery-show').animate({
				'margin-top': '-='+signle_height
				});
				step++;
				if(step>=single_gals_num-gals_show_num)
				{
					$('.thumbnail-gallery-wrap-ctrl b').addClass('disabled');
				}
				if(step>=1)
				{
					$('.thumbnail-gallery-wrap-ctrl i').removeClass('disabled');
				}
			}
			return false;
		});
		$('.thumbnail-gallery-wrap-ctrl i').click(function()
		{
			if(step > 0)
			{
				$('.thumbnail-gallery-show').animate({
				'margin-top': '+='+signle_height
				});
				step--;
				if(step<=0)
				{
					$('.thumbnail-gallery-wrap-ctrl i').addClass('disabled');
				}
				if(step<single_gals_num-gals_show_num)
				{
					$('.thumbnail-gallery-wrap-ctrl b').removeClass('disabled');
				}
			}
			return false;
		});	
	}

	// Scroll Top
	$(function() {
		var $sTop = $('#sTop');
		$sTop.on('click', function(e){
			e.preventDefault();
			$('html, body').stop(true).animate({scrollTop:0},800,'swing');
		});

		$(window).on('scroll',function() {
			var position = $(window).scrollTop();
			if( position > 200 ) 
				$sTop.show();
			else
				$sTop.hide();
		});
	});

	//get URL params
	var urlParams = {};
	var e,
	a = /\+/g,  // Regex for replacing addition symbol with a space
	r = /([^&=]+)=?([^&]*)/g,
	d = function (s) { return decodeURIComponent(s.replace(a, " ")); },
	q = window.location.search.substring(1);
	while (e = r.exec(q))
		urlParams[d(e[1])] = d(e[2]);
  
	//decide color
	var color = urlParams&&urlParams['color'] ? urlParams['color']:'';
	//if no color is specified by url, grey is selected by default
	if(color.length == 0 && window.location.pathname == "/products/misfit-shine") 
	{
		$('.color-button.grey').click();
	}
	else 
	{
		var aimcolor = $('.color-button.'+color);
		if( aimcolor.length != 0 )
		{
			aimcolor.click();
			$('#product-select-118521176-option-0 option').each(function()
			{
				var self = $(this);
				if($(this).attr('value').toLowerCase()==color)
				{
					self.attr('selected','selected').parent().change();
				}
			});
		}  
	}/**/
});

function checkVisible( elm, eval ) 
{
	eval = eval || 'visible';
	var vpH = $(window).height(); // Viewport Height
	var st = $(window).scrollTop(); // Scroll Top
	var y = $(elm).offset().top;
	var elementHeight = $(elm).height();
	
	var sCurObjId = $(elm).attr('id');

	if( eval == 'visible' )
	{
		// mark an object is on viewport
		if( (y < (vpH + st)) && (y > (st - elementHeight)) )
		{
			var bChecked = false;
			
			if( g_aParam.length > 0 )
			{
				for( var i in g_aParam )
				{
					if( g_aParam[i] == sCurObjId )
					{
						bChecked = true;
						break;
					}
				}
			}
			
			if( !bChecked )
			{
				// send log
//console.log( sCurObjId );
				//ga( 'send', 'pageview', '/buynow?section=function' );
				ga( 'send', 'event', sCurObjId, 'viewed', {'nonInteraction': 1});
				g_aParam[g_aParam.length] = sCurObjId;
			}
		}
	}
	if( eval == 'above' ) 
		return ((y < (vpH + st)));
}