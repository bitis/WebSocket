# WebSocket Server

纯PHP实现

## 使用

### 启动服务端
```php
// 监听本地的12345端口
new WebSocket("localhost",12345);
```
### 客户端 （使用JS）

```js
ws = new WebSocket('ws://localhost:12345');
ws.onmessage = function(msg) {console.log(msg.data)};

// 发送消息
ws.send('MSG');
```

## 参考资料

> [编写 WebSocket 服务器 - Web API 接口 | MDN](https://developer.mozilla.org/zh-CN/docs/Web/API/WebSockets_API/Writing_WebSocket_servers)