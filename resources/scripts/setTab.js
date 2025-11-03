import { ref } from 'vue';

export const activeTab = ref("altaListas");

export const setTab = (tab) => {
  activeTab.value = tab;
};
