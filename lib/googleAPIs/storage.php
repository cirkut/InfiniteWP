<?php
/*
 * Copyright 2012 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Simple classes to store a value in a PHP sessions or in browser cookies.
 * You can add your own classes to persist values through other mechanisms
 * like in a SQLLite database.
 * @author Nick Mihailovski (api.nickm@gmail.com)
 */

//session_start();

/**
 * Use PHP sessions to store values.
 */
class apiSessionStorage {
    
    public $dataStorage = null;
    
     /*
     * Constructure for apiSessionStorage
     */
    function __construct() {
        $this->dataStorage =  new apiDatabaseStorage();
    }

  /**
   * Sets a value in a PHP session.
   * @param string $value The value to set in the session.
   */
  public function set($value) {
      $this->dataStorage->set($value);
    //$_SESSION['access_token'] = $value;
  }

  /**
   * @return string The value stored in the session.
   */
  public function get() {
    return $this->dataStorage->get();
    //return $_SESSION['access_token'];
  }

  /**
   * Deletes the value from the session.
   */
  public function delete() {
    $this->dataStorage->delete();
    //unset($_SESSION['access_token']);
  }
}

/**
 * Use browser cookies to store values.
 */
class apiCookieStorage {
    
    public $dataStorage;
    
    /*
     * Constructure for apiCookieStorage
     */
    function __construct() {
        $this->dataStorage =  new apiDatabaseStorage();
    }

  /**
   * Sets a cookie called access_token to expire for 1 hour in the
   * future.
   * @param string The value to store in the cookie.
   */
  public function set($value) {
    $this->dataStorage->set($value);
    //setcookie('access_token', urlencode($value), time() + 3600);
  }

  /**
   * @return string The value stored in the cookie.
   */
  public function get() {
    return $this->dataStorage->get();
    //return urldecode($_COOKIE['access_token']);
  }

  /**
   * Deletes the cookie. Browsers delete cookies when their expiration
   * is set in the past.
   */
  public function delete() {
    $this->dataStorage->delete();
    //setcookie('access_token', '', time() - 100);
  }
}

class apiDatabaseStorage {
    
   /**
   * Sets a value in a Database.
   * @param string $value The value to set in the Database.
   */
    public function set($value) {
        updateOption('google_access_token',$value);
    }
    
   /**
   * @return string The value stored in the Database.
   */
    public function get() {
        return getOption('google_access_token');
    }
    
   /**
   * Deletes the value from the Database.
   */
    public function delete() {
        deleteOption('google_access_token');
    }
}

