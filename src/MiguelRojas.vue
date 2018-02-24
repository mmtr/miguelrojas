<template>
  <div class="miguel-rojas">
    <header class="header">
      <router-link to="home" class="header__logo-link">
        <img src="@/assets/miguelrojas.png" alt="Miguel Rojas" class="header__logo">
      </router-link>
    </header>
    <router-view/>
  </div>
</template>

<style lang="scss">
  @import url('https://fonts.googleapis.com/css?family=Quicksand:300,400,700');

  *,
  *::before,
  *::after {
    box-sizing: border-box;
  }

  html {
    font-family: sans-serif;
    line-height: 1.15;
    -webkit-text-size-adjust: 100%;
    -ms-text-size-adjust: 100%;
    -ms-overflow-style: scrollbar;
    -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
  }

  body {
    margin: 0;
    font-family: 'Quicksand', sans-serif;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5rem;
    color: white;
    text-align: left;
    background: #2f2f2f url('~@/assets/bg.svg') no-repeat center center fixed;
    background-size: cover;
  }

  .header {
    position: fixed;
    z-index: 1;
    padding: 1rem;
    width: 100%;
    background-color: rgba(#2f2f2f, 0.8);
    box-shadow: 0 0 10px #1f1f1f;

    .header__logo-link {
      display: inline-block;
    }

    .header__logo {
      max-height: 30px;
      vertical-align: middle;
    }
  }
</style>
