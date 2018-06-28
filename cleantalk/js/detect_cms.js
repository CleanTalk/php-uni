var request = new XMLHttpRequest();
request.open("GET", "cleantalk/js/cms_list.json", false);
request.send(null)
var apps = JSON.parse(request.responseText).apps;
var myCookies = getCookies();
console.log(myCookies);
var cms_found = false; var cms_name = "";
var index_file = file_get_contents(window.location.origin+"/index.php");
for (app in apps)
{
	if (apps[app]['html'] !== undefined)
	{
		if (Array.isArray(apps[app]['html']))
		{
			for (html_patters in apps[app]['html'])
			{
				if (index_file.indexOf(apps[app]['html'][html_patters]) > -1)
				{
					cms_found = true;
					cms_name = app;

				}
			}
		}
		else
		{
			if (index_file.indexOf(apps[app]['html']) > -1)
			{
				cms_found = true;
				cms_name = app;					
			}
		}			
	}
	if (!cms_found)
	{
		if (apps[app]['cookies'] !== undefined)
		{
			for (cookie in myCookies)
			{
				if (Object.keys(apps[app]['cookies']).indexOf(cookie) > -1)
					{
						cms_found = true; cms_name = app;
					}
			}		
		}		
	}
}
if (cms_found)
	alert(cms_name);
function getCookies()
{
  var pairs = document.cookie.split(";");
  var cookies = {};
  for (var i=0; i<pairs.length; i++){
    var pair = pairs[i].split("=");
    cookies[(pair[0]+'').trim()] = unescape(pair[1]);
  }
  return cookies;
}
function file_get_contents( url ) {	// Reads entire file into a string
	// 
	// +   original by: Legaev Andrey
	// %		note 1: This function uses XmlHttpRequest and cannot retrieve resource from different domain.

	var req = null;
	try { req = new ActiveXObject("Msxml2.XMLHTTP"); } catch (e) {
		try { req = new ActiveXObject("Microsoft.XMLHTTP"); } catch (e) {
			try { req = new XMLHttpRequest(); } catch(e) {}
		}
	}
	if (req == null) throw new Error('XMLHttpRequest not supported');

	req.open("GET", url, false);
	req.send(null);

	return req.responseText;
}