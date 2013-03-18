function toggleBlockDisplay(blockId)
{
  var c = $(blockId);
  if (c.style.display == 'none')
    c.style.display = 'block';
  else
    c.style.display = 'none';
}

function sR(type, id)
{
  var form = document.getElementById('rubricForm');
  form.elements['id'].value = id;
  form.elements['type'].value = type;
  form.submit();
}