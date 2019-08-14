import './bootstrap'
import Vue from 'vue'
// ルーティングの定義をインポートする
import router from './router'
// ルートコンポーネントをインポートする
import App from './App.vue'
import store from './store'

new Vue({
    el: '#app',
    router, // ルーティングの定義を読み込む
    store,
    components: { App }, // ルートコンポーネントの使用を宣言する
    template: '<App />' // ルートコンポーネントを描画する
})
