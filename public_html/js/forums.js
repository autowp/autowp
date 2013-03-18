function DeleteTopic(TopicId)
{
    if (!window.confirm('Подтвердите ваше желание удалить это обсуждение'))
        return;
    var DeleteTopicForm = document.getElementById('DeleteTopicForm');
    if (!DeleteTopicForm)
        return;
    DeleteTopicForm.topic_id.value=TopicId;
    DeleteTopicForm.submit();
}

function CloseTopic(TopicId)
{
  if (!window.confirm('Подтвердите ваше желание закрыть это обсуждение'))
    return;
  var CloseTopicForm = document.getElementById('CloseTopicForm');
  if (!CloseTopicForm)
    return;
  CloseTopicForm.topic_id.value=TopicId;
  CloseTopicForm.submit();
}

function OpenTopic(TopicId)
{
  var OpenTopicForm = document.getElementById('OpenTopicForm');
  if (!OpenTopicForm)
    return;
  OpenTopicForm.topic_id.value=TopicId;
  OpenTopicForm.submit();
}

function MoveTopic(TopicId)
{
  window.location = '/forums/move.topic/?topic_id='+TopicId;
}

function doMoveTopic(ThemeId)
{
  var MoveTopicForm = document.getElementById('MoveTopicForm');
  if (!MoveTopicForm) return;
  MoveTopicForm.theme_id.value=ThemeId;
  MoveTopicForm.submit();
}

function DeleteMessage(MessageId)
{
  if (!window.confirm('Подтвердите ваше желание удалить это сообщение'))
    return;
  var DeleteMessageForm = document.getElementById('DeleteMessageForm');
  if (!DeleteMessageForm)
    return;
  DeleteMessageForm.message_id.value = MessageId;
  DeleteMessageForm.submit();
}

function RestoreMessage(MessageId)
{
  var RestoreMessageForm = document.getElementById('RestoreMessageForm');
  if (!RestoreMessageForm)
    return;
  RestoreMessageForm.message_id.value = MessageId;
  RestoreMessageForm.submit();
}