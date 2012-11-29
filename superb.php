<?php
class Superb {
   private static $empty_tags = array(
      'br', 'hr', 'img', 'input', 'link', 'meta'
   );
   
   private $children = array();
   private $name;
   private $attrs = array();
   
   public function __construct($name, $inner = null) {
      $this->name = $name;
      if($inner !== null) {
         $this->attrs['%inner'] = $inner;
      }
   }
   
   public function add($child) {
      $this->children = array_merge($this->children, (array)$child);
   }
   
   public function set($attr, $val) {
      $this->attrs[$attr] = $val;
   }
   
   private function get($attr, $default = false) {
      return isset($this->attrs[$attr]) ? $this->attrs[$attr] : $default;
   }
   
   private function is_empty_tag() {
      return in_array($this->name, self::$empty_tags);
   }
   
   public function as_string($indent = "") {
      $new_indent = $this->get('%indent', true) ? $indent . "   " : '';
      
      if($this->name == 'raw') {
         return $this->get('%entity', true) ? htmlentities($this->attrs['%inner']) : $this->attrs['%inner'];
      } else if($this->name == 'comment') {
         return "<!-- " . $this->attrs['%inner'] . " -->";
      } else {
         $inner = "";
         $attribs = "";

         if(count($this->children) && !$this->is_empty_tag()) {
            if(count($this->children) == 1 && $this->children[0]->name == 'raw') {
               /* if the only child is a raw, then we want to inline this.
                  TODO: in the future, should have more flexibile inline system */
               $inner = $this->children[0]->as_string($new_indent);
            } else {
               foreach($this->children as $child) {
                  $inner .= "\n" . $new_indent . ($this->get('%entity') ? 
                     htmlentities($child->as_string($new_indent)) : $child->as_string($new_indent));
               }
               $inner .= "\n" . $indent;
            }
         }
         
         foreach($this->attrs as $attr => $val) {
            /* ignore Superb specific attributes */
            if(!is_null($val) && $attr[0] != "%") {
               $attribs .= " " . $attr . "='" . $val . "'";
            }
         }
         
         if($this->is_empty_tag()) return "<" . $this->name . $attribs . " />";
         else return "<" . $this->name . $attribs . ">" . $inner . "</" . $this->name . ">";
      }
   }
   
   public function __toString() {
      return $this->as_string();
   }
}

class Sp {
   private static $aliases = array(
      'css' => array('%name' => 'link', 'rel' => 'stylesheet', 'type' => 'text/css', 'href' => null),
      'js' => array('%name' => 'script', 'type' => 'text/javascript', 'src' => null),
   );
   
   private $top_level_su = array();
   
   public function get_top_su() {
      return $this->top_level_su;
   }
   
   public static function parse_options($su, $options, $default = '') {
      foreach($options as $attr => $val) {
         if(empty($val)) $val = $default;
         $su->set($attr, $val);
      }
      return $su;
   }
   
   private function parse_args($name, $args, $su = null) {
      /* each arg can only be one of the following:
         1. string: raw text enclosed by the tag (inner html)
         2. array: contains optional attributes (e.g. class, id, name)
         3. function: calls the function, with any markup added to this node */
      if(empty($su)) $su = new Superb($name); /* use existing Superb object? */
      if(count($args)) {
         $su_children = array();
         foreach($args as $arg) {
            if(is_a($arg, 'Superb')) {
               $su_children[] = $arg;
               
               /* since $arg is now a child of this Superb object, 
                  we must remove it from top_level_su as it is no longer 'top level' */
               if(($key = array_search($arg, $this->top_level_su)) !== false) {
                   unset($this->top_level_su[$key]);
               }
            } else if(is_a($arg, 'Sp')) {
               $su_children = array_merge($su_children, $arg->get_top_su());
            } else if(is_callable($arg)) {
               /* given a function, so call it with a new Superb object as parameter */
               call_user_func($arg, ($sp = new Sp()));
               if($sp && is_a($sp, 'Sp')) {
                  /* add any markup created by the Superb object passed to the function */
                  $su_children = array_merge($su_children, $sp->get_top_su());
               }
            } else if(is_string($arg)) {
               /* treat as a Sp::raw tag */
               if($name == 'raw' || $name == 'comment') $su->set('%inner', $arg);
               else $su_children[] = new Superb('raw', $arg);
            } else if(is_array($arg)) {
               /* optional attributes */
               $su = self::parse_options($su, $arg);
            }
         }
         
         $su->add($su_children);
      }

      /* we record what markup has been generated by this particular Sp object */
      $this->top_level_su[] = $su;
      
      return $su;
   }
   
   /* triggered when something like $sp->table() is called. */
   public function __call($name, $args) {
      if(isset(self::$aliases[$name]) && count($args)) {
         /* use the first arg as parameter of the alias, then parse the su object as normal */
         return $this->parse_args(self::$aliases[$name]['%name'], array_slice($args, 1),
            self::parse_options(new Superb(self::$aliases[$name]['%name']),  self::$aliases[$name], $args[0]));
      }
      
      return $this->parse_args($name, $args);
   }
   
   // triggered when something like Sp::table() is called
   public static function __callStatic($name, $args) {
      $sp = new Sp($name);
      return $sp->__call($name, $args);
   }
}