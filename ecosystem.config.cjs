module.exports = {
  apps: [
    {
      name: 'reverb',
      script: 'artisan',
      interpreter: 'php',
      args: 'reverb:start',
      autorestart: true,
      watch: false,
    },
  ],
};