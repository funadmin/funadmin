import NProgress from 'nprogress';

NProgress.configure({
  easing: 'ease',
  speed: 400,
  showSpinner: false,
  trickleSpeed: 200,
  minimum: 0.3
});

export default NProgress;
