import Flickr from 'flickr-sdk';

/**
 * Mixin for setting up flickr during the creation of Vue components
 */
const FlickrMixin = {
  created() {
    this.initializeFlickr();
  },

  methods: {
    /**
     * Initializes Flickr in this.flickr
     */
    initializeFlickr() {
      this.flickr = new Flickr(process.env.VUE_APP_FLICKR_API_KEY);
    },

    /**
     * Gets the source of the given photo in max resolution
     *
     * @async
     * @param photoId {Integer} ID of the photo
     * @returns {Promise<String>} URL with the source of the image
     */
    async getPhotoSource(photoId) {
      return new Promise((resolve) => {
        this.flickr.photos.getSizes({
          photo_id: photoId,
        }).then((getSizesResponse) => {
          const { source } = getSizesResponse.body.sizes.size.pop();
          this.preloadImage(source).then(() => {
            resolve(source);
          });
        });
      });
    },

    /**
     * Pre-loads the given URL image source in background
     *
     * @async
     * @param source {String} The URL image source to be pre-loaded
     * @returns {Promise<String>} URL of the image when it has been pre-loaded
     */
    async preloadImage(source) {
      return new Promise((resolve) => {
        const img = new Image();
        img.addEventListener('load', () => {
          resolve();
        });
        img.src = source;
      });
    },
  },
};

export default FlickrMixin;
