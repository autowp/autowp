function setArticleEnabled(articleId, enabled)
{
  var sUrl = "/ajax/moder/articles.php?action=setArticleEnabled&articleId="+articleId+'&enabled='+(enabled ? '1' : '0');

	var callback = {
		success: function(oResponse) {
			if (oResponse.responseText == 'enabled')
			{
			  $('#article'+oResponse.argument.articleId+'enabled').checked = true;
			}
			else if (oResponse.responseText == 'disabled')
			{
			  $('#article'+oResponse.argument.articleId+'enabled').checked = false;
			}
			else
			  alert(oResponse.responseText);
		},
		failure: function(oResponse) {
			alert(oResponse.responseText);
		},
		argument: {
			"articleId" : articleId
		},
		timeout: 7000
	};
	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
}



var panel;
var tree;

function nodeCheckClick(node)
{
  var action = null;
  var id = null;

  switch (node.data.type)
  {
    case 'car':
      action = 'setArticleCarLink';
      id = node.data.carId;
      break;

    case 'brand':
      action = 'setArticleBrandLink';
      id = node.data.brandId;
      break;

    case 'engine':
      action = 'setArticleEngineLink';
      id = node.data.engineId;
      break;

    case 'model':
      action = 'setArticleModelLink';
      id = node.data.modelId;
      break;

    case 'designProject':
      action = 'setArticleDesignProjectLink';
      id = node.data.designProjectId;
      break;

    case 'twinsGroup':
      action = 'setArticleTwinsGroupLink';
      id = node.data.twinsGroupId;
      break;
  }

  var sUrl = "/ajax/moder/articles.php?action="+action+"&articleId="+panel.autowpArticleId+'&id='+id+'&checked='+(node.checked ? '1' : '0');

	var callback = {
		success: function(oResponse) {
			//alert(oResponse.responseText);
		},
		failure: function(oResponse) {
			alert(oResponse.responseText);
		},
		argument: {
			"node": node
		},
		timeout: 7000
	};
	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
}

function showSelector(id)
{
  // Instantiate a Panel from markup
  panel = new YAHOO.widget.Panel("articleAddLinkPanel", { width:"500px", visible:true, constraintoviewport:true } );
  panel.setHeader("Добавить связи");
  panel.setBody('<div id="artcleAddLinkTree"></div>');
  panel.setFooter("Отметьте галочками связанное со статьей");
  panel.render();
  panel.autowpArticleId = id;

	tree = new YAHOO.widget.TreeView($('#artcleAddLinkTree'));
	tree.subscribe('checkClick', nodeCheckClick);

	tree.setDynamicLoad(loadNodeData, 0);
	var root = tree.getRoot();
	new YAHOO.widget.TextNode({label:'к бренду/модели/автомобилю',to:"toTree"}, root, false);
	new YAHOO.widget.TextNode({label:'к группе близнецов',to:"toTwinsGroups"}, root, false);
	tree.draw();


  /*var url = '/ajax/moder/articles.php?action=getArticleBrandSelector&articleId='+id;

  new Ajax.Request(url,
    {
      method: 'get',
      onSuccess:
        function(transport) {
          hideSelector();
          $('#artclesBrandsSelector').style.display = 'block';
          var data = eval(transport.responseText);
          for (var key in data)
          {
            new YAHOO.widget.TextNode(data[key].name, tree, false);
          }
        }
    }
  );*/


}


function loadNodeData(node, fnLoadComplete)
{
	// ПОДГРУЗКА СПИСКА МОДЕЛЕЙ
	if (node.data.to == 'toTree')
	{
	  if (node.data.ch == null)
  	{
  	  //prepare URL for XHR request:
    	var sUrl = "/ajax/moder/articles.php?action=getBrandsChars&articleId="+panel.autowpArticleId;

    	//prepare our callback object
    	var callback = {

    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			  new YAHOO.widget.TextNode({
    			    label : data[i],
    			    to : node.data.to,
    			    ch : data[i]
    			  }, node, false);

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
  	else if (node.data.brandId == null)
  	{
    	var sUrl = "/ajax/moder/articles.php?action=getBrands&articleId="+panel.autowpArticleId+'&char='+encodeURI(node.data.ch);

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			  new YAHOO.widget.CheckboxNode({
    			    label:data[i].name,
    			    to:node.data.to,
    			    ch:node.data.ch,
    			    brandId:data[i].id,
    			    type:'brand'
    			  }, node, false, data[i].checked);

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
  	else if (node.data.type == null || node.data.type == 'brand')
  	{
  	  var sUrl = "/ajax/moder/articles.php?action=getBrandChilds&articleId="+panel.autowpArticleId+'&char='+encodeURI(node.data.ch)+'&brandId='+encodeURI(node.data.brandId);

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			{
    			  var tempNode = new YAHOO.widget.CheckboxNode({
    			    label : data[i].name,
    			    to : node.data.to,
    			    ch : node.data.ch,
    			    brandId : node.data.brandId,
    			    modelId : data[i].modelId,
    			    designProjectId : data[i].designProjectId,
    			    carId : data[i].carId,
    			    type : data[i].type
    			  }, node, false, data[i].checked);
    			  if (data[i].type == 'car')
    			    tempNode.setFinalNode(true);
    			}

    			new YAHOO.widget.TextNode({
  			    label : 'Концепты и прототипы',
  			    to : node.data.to,
  			    ch : node.data.ch,
  			    brandId : node.data.brandId,
  			    type : 'concepts'
  			  }, node, false);

  			  new YAHOO.widget.TextNode({
  			    label : 'Двигатели',
  			    to : node.data.to,
  			    ch : node.data.ch,
  			    brandId : node.data.brandId,
  			    type : 'engines'
  			  }, node, false);

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
  	else if (node.data.type == 'model')
  	{
  	  var sUrl = "/ajax/moder/articles.php?action=getModelCars&articleId="+panel.autowpArticleId+'&char='+encodeURI(node.data.ch)+'&brandId='+encodeURI(node.data.brandId)+'&modelId='+node.data.modelId;

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			{
    			  var tempNode = new YAHOO.widget.CheckboxNode({
    			    label : data[i].name,
    			    to : node.data.to,
    			    ch : node.data.ch,
    			    carId : data[i].id,
    			    type : 'car'
    			  }, node, false, data[i].checked);
    			  tempNode.setFinalNode(true);
    			}

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
  	else if (node.data.type == 'designProject')
  	{
  	  var sUrl = "/ajax/moder/articles.php?action=getDesignProjectCars&articleId="+panel.autowpArticleId+'&char='+encodeURI(node.data.ch)+'&brandId='+encodeURI(node.data.brandId)+'&designProjectId='+node.data.designProjectId;

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			{
    			  var tempNode = new YAHOO.widget.CheckboxNode({
    			    label : data[i].name,
    			    to : node.data.to,
    			    ch : node.data.ch,
    			    carId : data[i].id,
    			    type : 'car'
    			  }, node, false, data[i].checked);
    			  tempNode.setFinalNode(true);
    			}

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
  	else if (node.data.type == 'concepts')
  	{
  	  var sUrl = "/ajax/moder/articles.php?action=getConceptCars&articleId="+panel.autowpArticleId+'&char='+encodeURI(node.data.ch)+'&brandId='+encodeURI(node.data.brandId);

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			{
    			  var tempNode = new YAHOO.widget.CheckboxNode({
    			    label : data[i].name,
    			    to : node.data.to,
    			    ch : node.data.ch,
    			    carId : data[i].id,
    			    type : 'car'
    			  }, node, false, data[i].checked);
    			  tempNode.setFinalNode(true);
    			}

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
  	else if (node.data.type == 'engines')
  	{
  	  var sUrl = "/ajax/moder/articles.php?action=getBrandEngines&articleId="+panel.autowpArticleId+'&char='+encodeURI(node.data.ch)+'&brandId='+encodeURI(node.data.brandId);

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			{
    			  var tempNode = new YAHOO.widget.CheckboxNode({
    			    label : data[i].name,
    			    to : node.data.to,
    			    ch : node.data.ch,
    			    engineId : data[i].id,
    			    type:'engine'
    			  }, node, false, data[i].checked);
    			  tempNode.setFinalNode(true);
    			}

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
	}
	else if (node.data.to == 'toTwinsGroups')
	{
	  if (node.data.brandId == null)
  	{
    	var sUrl = "/ajax/moder/articles.php?action=getTwinsGroupsBrandsChars&articleId="+panel.autowpArticleId;

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			  new YAHOO.widget.TextNode({
    			    label : data[i].name,
    			    to : node.data.to,
    			    brandId : data[i].id
    			  }, node, false);

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
  	else if (node.data.twinsGroupId == null)
  	{
  	  var sUrl = "/ajax/moder/articles.php?action=getTwinsGroups&articleId="+panel.autowpArticleId+'&brandId='+node.data.brandId;

    	var callback = {
    		success: function(oResponse) {
    			var data = eval("(" + oResponse.responseText + ")");
    			for (var i=0; i<data.length; i++)
    			{
    			  var tempNode = new YAHOO.widget.CheckboxNode({
    			    label : data[i].name,
    			    to : node.data.to,
    			    brandId : data[i].brandId,
    			    twinsGroupId : data[i].id,
    			    type : 'twinsGroup'
    			  }, node, false, data[i].checked);
    			  tempNode.setFinalNode(true);
    			}

    			oResponse.argument.fnLoadComplete();
    		},
    		failure: function(oResponse) {
    			oResponse.argument.fnLoadComplete();
    		},
    		argument: {
    			"node": node,
    			"fnLoadComplete": fnLoadComplete
    		},
    		timeout: 7000
    	};

    	YAHOO.util.Connect.asyncRequest('GET', sUrl, callback);
  	}
	}
}