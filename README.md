superb-php
==========

Generate nicely formatted HTML using PHP.

```php
include('superb.php');

echo
Sp::div(array('class' => 'container'),
   Sp::h1('Hi there!'),
   Sp::p('This is ', Sp::b('superb'), '!')
);
```

```html
<div class='container'>
   <h1>Hi there!</h1>
   <p>
      This is
      <b>Superb</b>
      !
   </p>
</div>
```