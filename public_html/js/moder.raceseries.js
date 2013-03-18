function sb(id)
{
    $("#addCarToRaceForm input[@name='brandId']").val(id);
    $("#addCarToRaceForm")[0].submit();
}

function sc(id)
{
    $("#addCarToRaceForm input[@name='carId']").val(id);
    $("#addCarToRaceForm")[0].submit();
}