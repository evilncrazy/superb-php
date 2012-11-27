superb-php
==========

Generate nicely formatted HTML using PHP.

```php
include('superb.php');

echo
Sp::div(
   Sp::h1('Hi there!'),
   Sp::p('This is ', Sp::b('superb'), '!')
);
```

```html
<div>
   <h1>Hi there!</h1>
   <p>
      This is 
      <b>superb</b>
      !
   </p>
</div>
```