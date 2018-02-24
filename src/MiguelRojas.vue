<template>
  <div class="miguel-rojas">
    <header class="miguel-rojas__header">
      <a href="#">
        <img src="@/assets/miguelrojas.png" alt="Miguel Rojas">
      </a>
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
</style>
