importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyDDuD7JPPP-9xGrI_J5aygfNvvSKjqUa6Y",
    authDomain: "",
    projectId: "portal-9524e",
    storageBucket: "",
    messagingSenderId: "",
    appId: "1:100879464630:android:2a56ef0c36129fd92cce17",
    measurementId: ""
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});