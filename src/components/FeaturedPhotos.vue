<template>
  <div class="featured-photos">
    <transition name="fade">
      <div class="featured-photos__loading" v-if="!activePhoto">
        <img src="@/assets/loading.svg" alt="Loading...">
      </div>
    </transition>
    <div v-for="photo in photos" :key="photo.id"
         class="featured-photos__photo" :class="{'featured-photos__photo--active': photo.active}"
         :style="{ backgroundImage: `url(${photo.source})` }"></div>
  </div>
</template>

<script>
import firebase from '@/mixins/firebase';
import flickr from '@/mixins/flickr';

export default {
  name: 'featured-photos',

  mixins: [firebase, flickr],

  data() {
    return {
      photos: [],
    };
  },

  computed: {
    activePhoto() {
      return this.photos.find(photo => photo.active && photo.source);
    },
  },

  created() {
    this.getPhotos();
  },

  methods: {
    /**
     * Gets the featured photos to be displayed from Firebase
     */
    getPhotos() {
      this.firebase.database().ref('photos').orderByChild('featured').equalTo(true)
        .once('value')
        .then((snapshot) => {
          this.photos = snapshot.val().filter(photo => photo.layout === 'landscape')
            .sort(() => Math.random() - 0.5);
          this.iteratePhotos();
        });
    },

    /**
     * Activates the next featured photo in order to display it
     *
     * @returns {Promise<Object>} Photo that has been activated
     */
    activateNextPhoto() {
      return new Promise((resolve) => {
        let nextPhotoIndex = null;

        if (!this.activePhoto) {
          nextPhotoIndex = 0;
        } else {
          nextPhotoIndex = this.photos.indexOf(this.activePhoto) + 1;
        }
        if (nextPhotoIndex >= this.photos.length) {
          nextPhotoIndex = 0;
        }
        const photo = this.photos[nextPhotoIndex];

        if (photo.source) {
          this.deactivatePhoto(this.activePhoto);
          this.$set(photo, 'active', true);
          resolve(photo);
        } else {
          this.getPhotoSource(photo.flickr_id)
            .then((source) => {
              this.deactivatePhoto(this.activePhoto);
              this.$set(photo, 'source', source);
              this.$set(photo, 'active', true);
              resolve(photo);
            });
        }
      });
    },

    /**
     * Deactivates the given featured photo
     */
    deactivatePhoto(photo) {
      if (photo) {
        this.$set(photo, 'active', false);
      }
    },

    /**
     * Activates a new photo every 5 seconds
     */
    iteratePhotos() {
      this.activateNextPhoto()
        .then(() => {
          setTimeout(() => {
            this.iteratePhotos();
          }, 5000);
        });
    },
  },
};
</script>

<style lang="scss">
  @import '~@/styles/_transitions.scss';

  .featured-photos {
    position: fixed;
    width: 100vw;
    height: 100vh;

    .featured-photos__loading {
      position: fixed;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100vw;
      height: 100vh;
    }

    .featured-photos__photo {
      position: fixed;
      width: 100vw;
      height: 100vh;
      background-size: cover;
      background-position: center;
      opacity: 0;
      transition: opacity 2s;

      &.featured-photos__photo--active {
        opacity: 1;
      }
    }
  }
</style>
