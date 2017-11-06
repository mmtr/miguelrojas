import Vue from 'vue';
import Router from 'vue-router';
import Home from '@/components/Home';
import Photos from '@/components/Photos';
import About from '@/components/About';

Vue.use(Router);

export default new Router({
  routes: [
    {
      path: '/',
      name: 'home',
      component: Home,
    },
    {
      path: '/photos',
      name: 'photos',
      component: Photos,
    },
    {
      path: '/about',
      name: 'about',
      component: About,
    },
  ],
});
