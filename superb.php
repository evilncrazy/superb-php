<?php
class Superb {
   private static $empty_tags = array(
      'br', 'hr', 'img', 'input', 'link', 'meta'
   );
   
   private $children = array();
   private $attrs = array();
   private $format;
   
   public function __construct($name, $inner = null) {
      $this->attrs['%name'] = $name;
      if($inner !== null) {
         $this->attrs['%inner'] = $inner;
      }
   }
   
   public function get_name() {
      return $this->attrs['%name'];
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
   
   private function is_trivial() {
      return $this->get_name() == 'text' || count($this->children) == 0 || 
             (count($this->children) == 1 && $this->children[0]->get_name() == 'text');
   }
   
   private function get_format() {
      /* a tag is inline if it has only trivial children */
      if(isset($this->format)) return $this->format;
      if(in_array($this->get_name(), self::$empty_tags)) return $this->format = 'empty';
      if($this->is_trivial()) return $this->format = 'inline';
      
      $all_tags = true;
      foreach($this->children as $child) {
         if(!$child->is_trivial() || $child->get_format() == 'block') return $this->format = 'block';
         if($child->get_name() == 'text') $all_tags = false;
      }
      
      if($all_tags) return $this->format = 'block';
      return $this->format = 'inline';
   }
   
   public function as_string($indent = "") {
      $new_indent = $this->get('%indent', true) ? $indent . "  " : '';
      
      if($this->get_name() == 'text') {
         return $this->get('%entity', true) ? htmlentities($this->attrs['%inner']) : $this->attrs['%inner'];
      } else if($this->get_name() == 'comment') {
         return "<!-- " . $this->attrs['%inner'] . " -->";
      } else {
         $inner = "";
         $attribs = "";

         if(count($this->children) && $this->get_format() != 'empty') {
            foreach($this->children as $child) {
               $inner .= ($this->get_format() == 'block' ? "\n" . $new_indent : '') . 
                         ($this->get('%entity') ? 
                           htmlentities($child->as_string($new_indent)) : $child->as_string($new_indent));
            }
            $inner .= ($this->get_format() == 'block' ? "\n" . $indent : '');
         }
         
         foreach($this->attrs as $attr => $val) {
            /* ignore Superb specific attributes */
            if(!is_null($val) && $attr[0] != "%") {
               $attribs .= ' ' . $attr . '="' . $val . '"';
            }
         }
         
         if($this->get_format() == 'empty') return "<" . $this->get_name() . $attribs . " />";
         else return "<" . $this->get_name() . $attribs . ">" . $inner . "</" . $this->get_name() . ">";
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
      'tag' => array('%name' => null)
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
               /* treat as a Sp::text tag */
               if($name == 'text' || $name == 'comment') $su->set('%inner', $arg);
               else $su_children[] = new Superb('text', $arg);
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