// ----------------------------------------------------------------------------
function showVotingResults(id)
{
    var container = $('#voting'+id)[0];
    if (!container)
        window.alert('Голосование не найдено');

    cleanNode(container);
    container.innerHTML = '<p style="text-align:center">Загрузка ...</p>';

    var ajax = getHTTPRequestObject();
    ajax.open('GET', '/ajax/voting.results.php?votingId='+id, true);
    ajax.onreadystatechange = function ()
    {
        if (ajax.readyState == 4)
        {
            if (ajax.status == 200)
            {
              cleanNode(this);
              this.innerHTML = ajax.responseText;
            }
        }
    }.bind(container);
    ajax.send(null);
}

// ----------------------------------------------------------------------------
function votingVote(id)
{
    var container = $('#voting'+id)[0];
    if (!container)
        window.alert('Голосование не найдено');

    var form = $('#voting'+id+'form')[0];
    if (!form)
        window.alert('Форма голосования не найдена');

    var multivariant = form.multivariant.value  > 0;

    var query = '';
    if (multivariant)
    {
        var cnt = 0;
        for (i=0; i<form.variantId.length; i++)
        {
            var checkbox = form.variantId.item(i);
            if (checkbox.checked)
            {
                query += '&variantId[]='+checkbox.value;
                cnt++;
            }
        }
        if (cnt <= 0)
        {
            window.alert('Выберите хотя бы один вариант ответа');
            return;
        }
    }
    else
    {
        var cnt = 0;
        for (i=0; i<form.variantId.length; i++)
        {
            var radio = form.variantId.item(i);
            if (radio.checked)
            {
                query += '&variantId='+radio.value;
                cnt++;
            }
        }
        if (cnt <= 0)
        {
            window.alert('Выберите вариант ответа');
            return;
        }
    }

    cleanNode(container);
    container.innerHTML = '<p style="text-align:center">Загрузка ...</p>';

    var ajax = getHTTPRequestObject();
    ajax.open('GET', '/ajax/voting.vote.php?votingId='+id+query, true);
    ajax.onreadystatechange = function ()
    {
        if (ajax.readyState == 4)
        {
            if (ajax.status == 200)
            {
              cleanNode(this);
              this.innerHTML = ajax.responseText;
            }
        }
    }.bind(container);
    ajax.send(null);
}