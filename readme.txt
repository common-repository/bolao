=== Bolão ===
Contributors: dgmike
Donate link: http://dgmike.wordpress.com
Tags: pool, gift, answer, questions, game
Requires at least: 2.5
Tested up to: 2.5
Stable tag: 2.3

Makes an intern game with subscrimers of your blog/site. Make pools and give to theyrs a value: users can vote in it. When you decide, close the pool. Users than answer the correct answer wins the points of that pool. This users can exchange their points for gifts.

== Description ==

With this plugin is possible to make game between users of wordpress.

1. Integrated with the system for users of WP (subscribers).
1. The administrator creates a pull, where he defines a question and a series of answers:

  Who gonna win the race in Canada with F1?

  * Rubens Barrichelo
  * Felipe Massa
  * Nelson Piquet
  * Ayrton Senna

1. The questions are closed by the administrator when the result comes out.
1. Users who check the winner earning points X defined by default, which can be customized by question.
1. Users accompanying the points earned on your profile.
1. In future, these users can exchange points for gifts on the site.

---

Com este plugin é possivel fazer bolão entre os usuários do wordpress.

1. Integrado com o sistema de usuários do WP (assinantes).
1. O administrador cria um bolão como se fosse uma enquete, onde ele define uma pergunta e uma série de respostas:

  Quem var ganhar a corrida do Canadá de F1?

  * Rubens Barrichelo
  * Felipe Massa
  * Nelson Piquet
  * Ayrton Senna

1. As perguntas são fechadas pelo administrador quando sai o resultado.
1. Os usuários que marcarem o vencedor ganham X pontos definidos por padrão, que podem ser customizados por pergunta.
1. Os usuários acompanham os pontos ganhos no seu perfil.
1. No futuro, estes usuários poderão trocar pontos por brindes no site.

== Installation ==

1. Upload `bolao.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure yours pools and gifts in `Bolão` options
1. Users can vote and request a gift on they're Bolão options
1. Chekout gifts for your personal control
1. If you want to show your last pool on your template, use the `bolao_widget` function.

== Frequently Asked Questions ==

= Can I put my last pool on my theme? =

Yes, just put this lines on yout template:

`<?php if (function_exists('bolao_widget')) bolao_widget(); ?>`

= Can I customize my Bolão widget? =

Of course. You can pass an array of options to your widget function. By default, we have this options:

`
array (
  'title'            => 'Bolao',
  'title_start'      => '<h2 class="bolao_title">',
  'title_end'        => '</h2>',
  'question_start'   => '<li class="bolao_question"><h3>',
  'question_end'     => '</h3></li>',
  'options_start'    => '<ul>',
  'opt_start'        => '<li class="bolao_option {EVEN-ODD}">',
  'opt_end'          => '</li>',
  'options_end'      => '</ul>',
  'submit_start'     => '<li class="bolao_submit">',
  'submit_end'       => '</li>',
  'print'            => true,
)
`

So you can pass an array with any of these options, like this.

`
bolao_widget(array (
  'title'            => 'Make your Choice',
  'question_start'   => '<h3>',
  'question_end'     => '</h3>',
  'options_start'    => '<div>',
  'opt_start'        => '<li>',
  'opt_end'          => '</li>',
  'options_end'      => '</div>',
));
`

And this generates this widget:

`
<h2 class="bolao_title">Make your Choice</h2>


<form action="" method="get" class="bolao_form">
  <input type="hidden" name="handle" value="handle" />
  <input type="hidden" name="details " value="details " />

  <div>
      <h3>Whats the color of the white horse of Napolian?</h3>
      <li>
        <label for="bolao_option_4873447a33954">
          <input type="radio" name="item" value="3" id="bolao_option_4873447a33954"  />
          Black
        </label>
      </li>
      <li>

        <label for="bolao_option_4873447a33964">
          <input type="radio" name="item" value="4" id="bolao_option_4873447a33964"  />
          White
        </label>
      </li>
      <li>
        <label for="bolao_option_4873447a3396c">
          <input type="radio" name="item" value="5" id="bolao_option_4873447a3396c"  />
          Gray
        </label>

      </li>
      <li>
        <label for="bolao_option_4873447a33974">
          <input type="radio" name="item" value="6" id="bolao_option_4873447a33974"  />
          Purple
        </label>
      </li>
      <li class="bolao_submit"><button type="submit" name="vote" value="Vote">Vote</button></li>
  </div>

</form>
`


== Screenshots ==

1. The user resume of pools, here he see the oppened and closed pools.
2. Details of pool for your users, here he can vote it.
3. Stuff screen, the user can request a gift for him.
4. Admin resume.
5. Admin details of pool. A pool can't be edited for security of users, so you can only delete or close a pool.
6. Creating a new pool.
7. List of stuffs.
8. New stuff
9. Details of requesteds stuffs, for your internal control.
10. Code generated by `bolao_widget` function