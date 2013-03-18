var defaultPrefix = 'http://autowp.ru';
var niceTitle;
var currentElement = null;
var timer = null;

initNiceTitles();

function initNiceTitles()
{
  if (!document.createElement)
    return;

  niceTitle = document.createElement("div");
  niceTitle.style.position = "absolute";
  niceTitle.style["float"] = "left";
  
  pushHandler(document, "mouseover", showNiceTitle);
  pushHandler(document, "focus", showNiceTitle);

  pushHandler(window, "blur", hideNiceTitle);
}

function findPosition(node)
{
  if (node.offsetParent)
  {
    for(var posX = 0, posY = 0; node.offsetParent; node = node.offsetParent)
    {
      posX += node.offsetLeft;
      posY += node.offsetTop;
    }
    return [posX, posY];
  }
  else
    return [node.x, node.y];
}

function createParagraph(text, className)
{
  var p = document.createElement("p");
  p.className = className;
  text = text.split("\n");
        if (typeof(text.length) == 'number')
  {
      for (var i=0; i<text.length; i++)
    {
      p.appendChild(document.createTextNode(text[i]));
      if (i < text.length-1)
        p.appendChild(document.createElement("br"));
    }
  }
  return p;
}

function showNiceTitle(e)
{
  e = e || window.event;
  if (!e || typeof(window.currentElement) == 'undefined')
    return;

  var element = e.target || e.srcElement;

  while (element)
  {
    if (element.nodeType == 1)
    {
      if (element.getAttribute("title"))
      {
        element.setAttribute("nicetitle", element.getAttribute("title"));
        element.setAttribute("title", "");
        element.setAttribute("nicedelay", 0);
        break;
      }
    }
    element = element.parentNode;
  }

  if (!element || element == currentElement)
    return;

  if (timer)
  {
    clearTimeout(timer);
    timer = null;
  }
  
  if (element.tagName.toLowerCase() != 'a')
    return;

  currentElement = element;
  pushHandler(element, "mouseout", hideNiceTitle);
  pushHandler(element, "blur", hideNiceTitle);

  var pos = findPosition(element);
  niceTitle.className = "nicetitle " + element.tagName.toLowerCase() + "NT";
  
  while (niceTitle.firstChild)
    niceTitle.removeChild(niceTitle.firstChild);

  niceTitle.appendChild(createParagraph(element.getAttribute("nicetitle")), "titletext");
  var delay = element.getAttribute("nicedelay");

  element = e.target || e.srcElement;

  while (element && (element.nodeType != 1 || !element.getAttribute("href")))
    element = element.parentNode;

  if (element)
  {
    niceTitle.className += " aNT";
  }

  if (delay)
    timer = setTimeout("doShowNiceTitle(["+pos[0]+","+pos[1]+"])",delay);
  else
    doShowNiceTitle(pos);
}

function doShowNiceTitle(pos)
{
  timer = null;
 
  niceTitle.style.left = "0px";
  niceTitle.style.top = "0px";
  niceTitle.style.visibility = "hidden";
  setTimeout("makeNiceTitleVisible(["+pos[0]+","+pos[1]+"])", 0);
  document.body.appendChild(niceTitle);
}

function makeNiceTitleVisible(pos)
{
  pos[0] += 15;
  pos[1] += 35;

  var innerWidth = null;
  if (window.innerWidth)
    innerWidth = window.innerWidth;
  else if (document.body && document.body.clientWidth)
    innerWidth = document.body.clientWidth;

  if (innerWidth && niceTitle.offsetWidth && niceTitle.offsetWidth + pos[0] + 30 > innerWidth)
    pos[0] = Math.max(innerWidth - niceTitle.offsetWidth - 30, 30);

  niceTitle.style.left = pos[0] + 'px';
  niceTitle.style.top = pos[1] + 'px';
  niceTitle.style.visibility = "visible";
}

function hideNiceTitle(e)
{
  if (timer)
  {
    clearTimeout(timer);
    timer = null;
  }
  if (typeof(window.currentElement) == 'undefined')
    return;

  if (currentElement && niceTitle.parentNode)
  {
    niceTitle.parentNode.removeChild(niceTitle);
    removeHandler(currentElement, "mouseout", hideNiceTitle);
    removeHandler(currentElement, "blur", hideNiceTitle);
    currentElement = null;
  }
}
