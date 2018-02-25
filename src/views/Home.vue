<template>
  <div class="home">
    <featured-photos/>
  </div>
</template>

<script>
import FeaturedPhotos from '@/components/FeaturedPhotos.vue';

export default {
  name: 'home',
  components: {
    FeaturedPhotos,
  },
};
</script>
