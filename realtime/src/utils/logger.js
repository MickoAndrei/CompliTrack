function info(message, details = {}) {
  console.info(JSON.stringify({ level: 'info', message, ...details }));
}

function error(message, exception) {
  console.error(JSON.stringify({
    level: 'error',
    message,
    error: exception instanceof Error ? exception.message : String(exception),
  }));
}

module.exports = { info, error };