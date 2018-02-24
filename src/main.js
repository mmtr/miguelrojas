import Vue from 'vue';
import MiguelRojas from './MiguelRojas.vue';
import router from './router';

Vue.config.productionTip = false;

new Vue({
  router,
  render: h => h(MiguelRojas),
}).$mount('.js-miguel-rojas');
