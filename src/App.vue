<template>
  <div class="app">
    <header class="header">
      <div class="header__brand">
        <router-link :to="{ name: 'home' }">
          <img src="./assets/miguelrojas.png" alt="Miguel Rojas" class="header__brand__logo">
        </router-link>
      </div>
      <div class="header__menu">
        <router-link :to="{ name: 'photos' }" class="header__menu__item">Photos</router-link>
        <router-link :to="{ name: 'about' }" class="header__menu__item">About</router-link>
      </div>
    </header>
    <router-view/>
  </div>
</template>

<script>
export default {
  name: 'app',
};
</script>

<style src="./styles/global.scss" lang="scss"></style>

<style lang="scss" scoped>
.header {
  position: absolute;
  padding: 1rem;
  width: 100%;
  display: flex;
  justify-content: space-between;
  background: linear-gradient(to bottom, rgba(50, 50, 50, 0.5) 50%, rgba(50, 50, 50, 0));
  padding-bottom: 2rem;

  .header__brand__logo {
    height: 1.5rem;
    vertical-align: middle;
    display: block;
  }

  .header__menu__item {
    text-transform: uppercase;
    font-weight: $font-weight-light;
    margin-left: 1rem;
  }
}
</style>
