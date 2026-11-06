function makeNotification({
  recipientUserId,
  type,
  title,
  message,
  relatedCheckId,
  dedupeKey,
}) {
  return {
    recipientUserId,
    senderUserId: null,
    type,
    title,
    message,
    relatedCheckId,
    isRead: false,
    dedupeKey,
    createdAt: new Date(),
  };
}

module.exports = { makeNotification };