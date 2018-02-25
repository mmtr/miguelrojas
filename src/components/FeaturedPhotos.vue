<template>
  <div class="full-page-photo">
    <div class="full-page-photo__loading"
         :class="{ 'full-page-photo__loading--fade-out': !loading}">
      <img src="@/assets/loading.svg" alt="Loading...">
    </div>
    <div :style="{ backgroundImage: `url(${source})` }" class="full-page-photo__img"
         v-if="!loading"></div>
  </div>
</template>

<script>
import firebase from '../mixins/firebase';
import flickr from '../mixins/flickr';

export default {
  name: 'featured-photos',

  data() {
    return {
      photos: [],
      source: null,
    };
  },

  mixins: [firebase, flickr],

  computed: {
    loading() {
      return this.source === null;
    },
  },

  created() {
    this.getFeaturedPhotos();
  },

  methods: {
    /**
     * Gets the featured photos to be displayed from Firebase
     */
    getFeaturedPhotos() {
      this.$firebase.database().ref('photos').orderByChild('featured').equalTo(true)
        .once('value')
        .then((snapshot) => {
          this.photos = snapshot.val();
          this.getFeaturedPhoto();
        });
    },

    getFeaturedPhoto() {
      const photo = this.photos[Math.floor(Math.random() * this.photos.length)];
      this.getPhotoSource(photo.flickr_id)
        .then((source) => {
          this.source = source;
        });
    },
  },
};
</script>

<style lang="scss">

  @keyframes fadeIn {
    0% {
      opacity: 0;
    }

    100% {
      opacity: 1;
    }
  }

  @keyframes fadeOut {
    0% {
      opacity: 1;
    }

    100% {
      opacity: 0;
    }
  }

  .full-page-photo {
    position: fixed;
    width: 100vw;
    height: 100vh;
    top: 0;
    left: 0;

    .full-page-photo__loading {
      position: fixed;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;

      &.full-page-photo__loading--fade-out {
        animation-name: fadeOut;
        animation-duration: 2s;
      }
    }

    .full-page-photo__img {
      position: relative;
      width: 100%;
      height: 100%;
      background-size: cover;
      background-position: center;
      animation-name: fadeIn;
      animation-duration: 2s;
    }
  }
</style>
