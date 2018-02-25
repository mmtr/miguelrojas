import * as firebase from 'firebase';

const FirebaseMixin = {
  created() {
    this.initializeFirebase();
  },

  methods: {
    initializeFirebase() {
      const config = {
        apiKey: process.env.VUE_APP_FIREBASE_API_KEY,
        authDomain: process.env.VUE_APP_FIREBASE_AUTH_DOMAIN,
        databaseURL: process.env.VUE_APP_FIREBASE_DATABASE_URL,
        projectId: process.env.VUE_APP_FIREBASE_PROJECT_ID,
        storageBucket: process.env.VUE_APP_FIREBASE_STORAGE_BUCKET,
        messagingSenderId: process.env.VUE_APP_FIREBASE_MESSAGING_SENDER_ID,
      };

      if (!firebase.apps.length) {
        firebase.initializeApp(config);
      }

      this.$firebase = firebase;
    },
  },
};

export default FirebaseMixin;
