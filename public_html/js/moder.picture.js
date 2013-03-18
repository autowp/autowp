/*Selector.prototype.getDiv = function(object)
{
  var div = $('#selectorContainer');
  if (!div)
  {
    div = document.createElement('div');
    div.id = 'selectorContainer';
    div.style.display = 'none';
    div.style.position = 'absolute';
    div.style.left = 0;
    div.style.top = 0;
    div.style.width = '100%';
    div.style.height = '100%';
    div.style.verticalAlign = 'middle';
    div.style.backgroundColor = 'red';
    document.body.appendChild(div);
  }
  return div;
}

Selector.prototype.showSelector()
{
  this.getDiv().style.display = 'block';
  this.getDiv().innerHTML = 'Loading ...';
}    */

function movePictureToModelSelectModel(modelId)
{
    $("#movePictureToModelForm input[@name='modelId']").val(modelId);
    $("#movePictureToModelForm")[0].submit();
}

function movePictureToModelSelectBrand(brandId)
{
    $("#movePictureToModelForm input[@name='brandId']").val(brandId);
    $("#movePictureToModelForm")[0].submit();
}

function sb(id)
{
    $("#MovePictureToCarForm input[@name='BrandID']").val(id);
    $("#MovePictureToCarForm")[0].submit();
}

function sc(id)
{
    $("#MovePictureToCarForm input[@name='CarID']").val(id);
    $("#MovePictureToCarForm")[0].submit();
}

function SelectPictureBrand(id)
{
    $("#MovePictureToBrandForm input[@name='BrandID']").val(id);
    $("#MovePictureToBrandForm")[0].submit();
}

function SelectEngineBrand(id)
{
    $("#MovePictureToEngineForm input[@name='BrandID']").val(id);
    $("#MovePictureToEngineForm")[0].submit();
}

function SelectEngine(id)
{
    $("#MovePictureToEngineForm input[@name='EngineID']").val(id);
    $("#MovePictureToEngineForm")[0].submit();
}