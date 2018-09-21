# 宕机模式（服务端配置）

:::tip

此文档的示例代码使用`Nodejs`，仅供参考，部分方法需用户自己实现，具体实现可根据实际情况作调整。

:::

##  outage接口说明

用户服务器端应增加 `outage` 接口，此接口为`jsonp`类型， 接口地址以参数的形式传入前端验证码初始化函数中，进入宕机模式后由sdk调用。

前端初始化示例代码: 

```javascript
vaptcha({
    vid: '****',
    container: '#vaptcha-container',
    outage: '/outage', // 你服务端提供的接口地址，包含获取图片和验证轨迹两种操作
    mode: 'downtime' // 加入此参数直接进入宕机模式，用于调试，上线请删除
}).then(function(obj){
    obj.render()
})
```



## 接口参数

### 接受参数

| 参数名    | 类型     | 说明                                              |
| --------- | -------- | ------------------------------------------------- |
| callback  | `string` | jsonp回调函数名                                   |
| action    | `string` | 值为`get`获取验证图片，值为`verify`进行人机验证。 |
| v         | `string` | 轨迹参数                                          |
| challenge | `string` | 流水号                                            |



## get 获取图片操作

> `outage`接口的`aciton`值 为 `get`时的操作

获取流程：

1. 获取服务器宕机`key`

   请求 http://d.vaptcha.com/config ，此接口返回`json`数据，格式

   ```json
   {
       "key":"*************",
       "active": false
   }
   ```

   其中`key`即为宕机`key`，示例代码:

   ```javascript
   // getJson 方法需自行实现，获取接口的Json数据
   const result = getJson('http://d.vaptcha.com/config');
   const downtimeKey = result.key;
   ```

   `active`表示是否进入宕机，`true`表示进入宕机。

   ```javascript
   if(!result.active) {
       // vaptcha并未宕机，返回失败
       // 调试时需跳过此判断
   }
   ```

2. 生成随机数`randomStr`

   从“0123456789abcdef”中取4位16进制数的字符串`randomStr`，示例代码:

   ```javascript
   const getRandomStr = () => {
     const str = '0123456789abcdef'
     return Array(4).fill(0).map(v => str[Math.floor(Math.random() * 16)]).join('')
   }
   const randomStr = getRandomStr();
   ```

3. 生成验证图`imgid`

   取宕机`key`和`randomStr`的`md5`值作为验证图的`imgid`，示例代码:

   ```javascript
   const imgid = md5(key + radomStr);
   ```

4. 生成流水号`challenge`

   服务器端生成唯一字符串`challenge`，用于标识本次验证操作，如：GUID ，自增数等，官方的示例操作是将字符串作为键，`imgid`作为值存入`session`中，示例代码

   ```javascript
   const challenge = uniqueId();
   setSession(challenge, imgid, 3 * 60 * 1000); // challenge 3分钟有效期
   ```

   同一次验证多次请求图片，所以会带上上一次的`challenge`，`get`接口接受到参数`challenge`后，不用再次生成`challenge`，只需重新生成`imgid`。示例代码：

   ```javascript
   let challenge = getRequest('challenge');
   if(isEmpty(challenge)) {
       challenge = uniqueId();
   }
   setSession(challenge, imgid, 3 * 60 * 1000); // challenge 3分钟有效期
   ```

5. 返回`jsonp`格式数据，`code`为返回码，`0103`表示获取成功，`0104`表示获取失败。

   ```javascript
   callback({
       code: '0103'，
       imgid: imageid,
       challenge：challenge
   })
   ```

## verify 轨迹验证操作

> `outage`接口的`aciton`值 为 `verify`时的操作

用户在浏览器上完成人机验证后，前端sdk向outage接口发起验证请求。

验证流程：

1. 生成轨迹验证`validatekey`

   首先通过请求参数`challenge`获取`session`中缓存的`imgid`，再取请求参数`v`和`imgid`的`md5`值作为用于验证的`validatekey`，示例代码：

   ```javascript
   const challenge = getRequest('challenge');
   const v = getRequest('v');
   const imgid = getSession(challenge);
   const validatekey = md5(imgid + v);
   ```

2. 服务器端发起轨迹验证请求

   请求地址 "http://d.vaptcha.com/"，接口返回`json`数据，示例代码：

   ```javascript
   const result = getJson('http://d.vaptcha.com/' + validatekey);
   if(result.code === 200) { /*验证成功，返回token*/}
   else {/*验证错误*/}
   ```

3. 验证成功，返回`token`

   验证成功后生成一个唯一的`token`，存在服务端用于提交表单的验证，并将`token`返回，同时清除`chalenge`的`session`信息，示例代码：

   ```javascript
   clearSession(challenge);
   const token = uniqueId();
   setSession('token', token, 3 * 60 * 1000); // token 3分钟有效期
   ```

4. 返回验证结果
   返回 `jsonp` 格式数据，正确返回:

   ```javascript
   callback({
       code:'0103',
       token:validatetoken
   })
   ```

## 服务器端验证token

前端完成验证提交表单数据时，将 `token` 一并提交，服务器端验证通过 `token` 内容判断用户验证操作是否有效，`token`只可验证一次，验证通过后应清除`token`。示例代码:

```javascript
const token = getRequest('token');
if (getSession('token')) {
    clearSession('token');
    return /*pass*/
} else {
    return /*fail*/
}
```

## 官网文档地址
地址: [https://www.vaptcha.com/document/install#header-n0](https://www.vaptcha.com/document/install#header-n0)
