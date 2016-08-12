<?php return array (
// This is where the default configuration will go.  All comments will be
//   discarded upon configuration of the tool from the web UI.
// The lock on an account must be set to false before you can use it.
// It is recommended to change the 'password' before you set the lock to
//   false.
// If you have alternate login system, then you may use that.

// Permission levels:
// 0: full control
// 1: Lookup + Cache clearing
// 2: Just lookup

  'login' =>
  array(
    'required' => true, // True to require login, false for optional.
    'baseperm' => 2,    // If login not required, base permission to use.
                        //   (if login is not required)
    'duration' => 21,   // "Remember me" duration in days
  ),
  'user' => 
  array (
    # Optional: Change and set-up the admin user.
    'admin' =>
    array (
      # 1. Set the password.
      'pass' => 'password',
      # 2. Set this to false to allow account access from the web.
      'lock' => true,
      'perm' => 0, // 0 means full permission!
    ),
    # Optional: Configure other users here.  Create as many users as you want.
    'purgableuser' => 
    array (
      'pass' => 'examplepass',
      'lock' => true,
      'perm' => 1, // 1 is lookup tool access plus cache purging access.
    ),
    'user' => 
    array (
      'pass' => 'examplepass',
      'lock' => true,
      'perm' => 2, // 2 is just lookup tool access.
    ),
  ),
  # Optional: Configure navigation items here.
  'navbar' => 
  array (
    'Home' => '/',
    'BanManager' => '../banmanager/',
    'Dynmap' => 'http://127.0.0.1:8123/',
  ),
  # Optional: configure this just by using this file.
  'form' => 
  array (
    'dateFormat'   => 'll',
    'timeFormat'   => 'LTS',
    'timeDividor'  => 300,
    'pageInterval' => 25,
    'bukkitToMc'   => true,     // Translate Bukkit block/item names to MC name
  ),
  'copyright' => 'SimonOrJ, 2015-%year%',
);?>