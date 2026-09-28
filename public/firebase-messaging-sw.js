importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyDDuD7JPPP-9xGrI_J5aygfNvvSKjqUa6Y",
    authDomain: "mili-a2a33.firebaseapp.com",
    projectId: "mili-a2a33",
    storageBucket: "mili-a2a33.firebasestorage.app",
    messagingSenderId: "294177416704",
    appId: "1:294177416704:android:5a4be8ecc747a48e9e5ae5",
    measurementId: ""
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});