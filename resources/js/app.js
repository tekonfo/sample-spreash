import './bootstrap'
import Vue from 'vue'
// Vuetify
import Vuetify from 'vuetify'
import 'vuetify/dist/vuetify.min.css';
import 'material-design-icons-iconfont/dist/material-design-icons.css'
Vue.use(Vuetify);

// ルーティングの定義をインポートする
import router from './router'
// ルートコンポーネントをインポートする
import App from './App.vue'
import store from './store'

const createApp = async () => {
    await store.dispatch('auth/currentUser')

    new Vue({
        el: '#app',
        vuetify: new Vuetify(),
        router,
        store,
        components: { App },
        template: '<App />'
    })
}

createApp()
