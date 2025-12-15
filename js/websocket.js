const socket = new WebSocket("ws://localhost:8080");

socket.onopen = () => {
  console.log("WebSocket connected");
};

socket.onmessage = (event) => {
  showNotification(event.data);
};

function showNotification(message) {
  const box = document.getElementById("notification-box");
  const item = document.createElement("div");
  item.className = "notification-item";
  item.innerText = message;
  box.prepend(item);
}
