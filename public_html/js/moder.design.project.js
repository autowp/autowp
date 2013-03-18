function sb(id)
{
    $("#moveCarToDesignProjectForm input[@name='brandId']").val(id);
    $("#moveCarToDesignProjectForm")[0].submit();
}

function sc(id)
{
    $("#moveCarToDesignProjectForm input[@name='carId']").val(id);
    $("#moveCarToDesignProjectForm")[0].submit();
}