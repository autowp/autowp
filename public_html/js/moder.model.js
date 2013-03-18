function sb(id)
{
    $("#moveCarToModelForm input[@name='brandId']").val(id);
    $("#moveCarToModelForm")[0].submit();
}

function sc(id)
{
    $("#moveCarToModelForm input[@name='carId']").val(id);
    $("#moveCarToModelForm")[0].submit();
}