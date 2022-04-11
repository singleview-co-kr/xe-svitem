$(document).ready(function()
{
	var sItemTitle = '';
	$.each($('.mp-info-small-only'), function(){ 
		sItemTitle = $( 'h3', this ).html();
		if( sItemTitle.length > 7 )
		{
			var asWord = sItemTitle.split(' ');
			var nLength = asWord.length;
			var sBrTitle = '';
			for( var i = 0; i < nLength; i++ )
			{
				sBrTitle += asWord[i];
				if( i < nLength - 2 )
					sBrTitle += ' ';
				else if( i == nLength - 2 )
					sBrTitle += '<BR>';
			}
			$( 'h3', this ).html( sBrTitle );
//console.log( sBrTitle );
		}
	});
});