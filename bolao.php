<?php
/*
    Plugin Name: Bolão
    Description: Makes an intern game with subscrimers of your blog/site. Make pools and give to theyrs a value: users can vote in it. When you decide, close the pool. Users than answer the correct answer wins the points of that pool. This users can exchange their points for gifts.
    Version: 2.3
    Author: DGmike
    Author URI: http://dgmike.wordpress.com
 */

class Bolao {
  static $wpdb;
  static $info;
  static $current_user;

  static function _getAction(array $post) {
    foreach ($post as $key => $value)
      if (preg_match('/^(add|remove|submit)\d*?$/', $key))
        return $key;
    return '';
  }

  static function _getValues() {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    global $current_user;
    if ($current_user->user_level == 10) return array('', '');

    $result = Bolao::$wpdb->get_results(sprintf('SELECT SUM(value) AS valor FROM %sbolao_user_pool bup
                                                 JOIN %sbolao_pool bp on answer_id = right_answer_id AND bup.pool_id = bp.pool_id
                                                 WHERE user_id = %s', Bolao::$wpdb->prefix, Bolao::$wpdb->prefix, $current_user->ID));
    $valor_total = $result[0]->valor;
    $results = Bolao::$wpdb->get_results (sprintf('SELECT SUM(value) AS v FROM %sbolao_user_order NATURAL JOIN %sbolao_stuff WHERE user_id = %s', Bolao::$wpdb->prefix, Bolao::$wpdb->prefix, $current_user->ID));
    $stuff_sum = sizeof($results) ? number_format($results[0]->v,2) : 0;
    return array($stuff_sum, $valor_total);
  }

  static function init() {
    global $wpdb;
    Bolao::$wpdb = $wpdb;
    Bolao::$info['plugin_fpath'] = dirname(__FILE__);
    Bolao::$current_user = wp_get_current_user();
    add_action('admin_menu', array ('Bolao', 'mkMenuOptions'));
  }

  static function install () {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    Bolao::$wpdb->query (sprintf('
      CREATE TABLE %sbolao_pool (
        `pool_id`   INT NOT NULL auto_increment,
        `question`  VARCHAR(255) NOT NULL,
        `value`     FLOAT NOT NULL,
        `status`    INT NOT NULL,
        `right_answer_id` INT NOT NULL,
        PRIMARY KEY (`pool_id`)
      )
    ', Bolao::$wpdb->prefix));

    Bolao::$wpdb->query (sprintf('
      CREATE TABLE %sbolao_answer (
        `answer_id`  INT NOT NULL auto_increment,
        `pool_id`    INT NOT NULL,
        `answer`     VARCHAR(255) NOT NULL,
        PRIMARY KEY (`answer_id`)
      )
    ', Bolao::$wpdb->prefix));

    Bolao::$wpdb->query (sprintf('
      CREATE TABLE %sbolao_user_pool (
        `user_id`    INT NOT NULL,
        `pool_id`    INT NOT NULL,
        `answer_id`  INT NOT NULL,
        PRIMARY KEY (`user_id`, `pool_id`)
      )
    ', Bolao::$wpdb->prefix));

    Bolao::$wpdb->query (sprintf('
      CREATE TABLE %sbolao_stuff (
        `stuff_id`    INT NOT NULL auto_increment,
        `name`        VARCHAR(60) NOT NULL,
        `desc`        TEXT NOT NULL,
        `value`       FLOAT NOT NULL,
        PRIMARY KEY (`stuff_id`)
      )
    ', Bolao::$wpdb->prefix));

    Bolao::$wpdb->query (sprintf('
      CREATE TABLE %sbolao_user_order (
        `user_order_id` INT NOT NULL auto_increment,
        `user_id`       INT NOT NULL,
        `stuff_id`      INT NOT NULL,
        `status`        INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`stuff_id`)
      )
    ', Bolao::$wpdb->prefix));
  }

  static function uninstall () {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    foreach (array ('pool', 'answer', 'user_pool', 'stuff', 'user_stuff') as $item)
      Bolao::$wpdb->query (sprintf ('
        DROP TABLE %sbolao_%s
      ', Bolao::$wpdb->prefix, $item));
  }

  static function status ($silent = false, $post = array ()) {
    if (isset($_REQUEST) && sizeof($_REQUEST) > 0) $get = $_REQUEST;
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    if (isset ($get['handle'])) { Bolao::detail($get); return; }
    global $current_user;
    $status = ($current_user->user_level == 10) ? 'admin' : 'other';
    $tplObj = new FileReader(Bolao::$info['plugin_fpath'].'/resume.html');
    $lines = split ("\n", $tplObj->read($tplObj->length()));
    foreach ($lines as $k => $line) {
      if (preg_match ('/^\s*\[(admin|other)\]/', $line, $match)) {
        $opts[$match[1]] = preg_replace('/^\s*\[(loop|admin|other)\]/', '', $line);
        unset ($lines[$k]);
      }
    }
    $pools = Bolao::$wpdb->get_results (sprintf(
      'SELECT * FROM %sbolao_pool ORDER BY pool_id DESC',
      Bolao::$wpdb->prefix
    ));
    $extra='';
    $rows = array();
    foreach ($pools as $pool) {
      $tmp = $opts[$status];
      foreach ($pool as $k=>$v) {
        if ($k == 'status') {
          if ($v == 0) {
            $sts = __('<strong style="color:#008800">Opened</strong>');
            $uVoted = Bolao::$wpdb->get_results (sprintf('SELECT * FROM %sbolao_user_pool WHERE user_id = %s AND pool_id = %s', Bolao::$wpdb->prefix, $current_user->ID, $pool->pool_id));
            $uVoted = count($uVoted) > 0 ? __('<br/>You already voted.') : '';
            $res = $sts . $uVoted;
            $tmp = str_replace('{' . strtoupper($k) . '}', $res, $tmp);
          } else {
            $sts = __('<strong style="color:#CC0000">Closed</strong>');
            $uVotedQ = Bolao::$wpdb->get_results (sprintf('SELECT * FROM %sbolao_user_pool WHERE user_id = %s AND pool_id = %s', Bolao::$wpdb->prefix, $current_user->ID, $pool->pool_id));
            if (count($uVotedQ) > 0) {
              if ($uVotedQ[0]->answer_id == $pool->right_answer_id) {
                $uVoted = sprintf(__('<br/><em style="color:#282">You wins %s points.</em>'), $pool->value);
              } else {
                $ra = Bolao::$wpdb->get_results (sprintf ('SELECT * FROM %sbolao_answer WHERE answer_id = %s', Bolao::$wpdb->prefix, $pool->right_answer_id));
                $ya = Bolao::$wpdb->get_results (sprintf ('SELECT * FROM %sbolao_answer WHERE answer_id = %s', Bolao::$wpdb->prefix, $uVotedQ[0]->answer_id));
                $uVoted = vsprintf (__('<br/><span style="color:#822">Right answer: <strong>%2$s</strong><br/>Your vote: <strong>%1$s</strong></span>'),
                            array ($ya[0]->answer, $ra[0]->answer)
                          );
              }
            } else {
              $uVoted = '';
            }
            if ($current_user->user_level == 10) {
              $ra = Bolao::$wpdb->get_results (sprintf ('SELECT * FROM %sbolao_answer WHERE answer_id = %s', Bolao::$wpdb->prefix, $pool->right_answer_id));
              $uVoted = sprintf(__('<br/>Answer: <em style="color:#282">%s</em>'), $ra[0]->answer);
            }
            $res = $sts . $uVoted;
            $tmp = str_replace('{' . strtoupper($k) . '}', $res, $tmp);
          }
        } else {
        	$tmp = str_replace('{' . strtoupper($k) . '}', $v, $tmp);
        }
      }
      $rows[] = $tmp;
    }
    $return = '';
    foreach ($lines as $line) {
      if (preg_match('/^(\s*)\[loop\]/', $line, $match))
        $return .= "\n{$match[1]}" . implode ("\n{$match[1]}", $rows);
      else
        $return .= "\n$line";
    }
    $return = str_replace('{EXTRA}', $extra, $return);
    $return = str_replace('{DETAILS}', __('Details'), $return);
    $return = str_replace('{RESUME}', __('Resume of pools'), $return);
    $return = str_replace('{TITLE}', __('Title'), $return);
    $return = str_replace('{STATUS}', __('Status'), $return);
    $return = str_replace('{NONCE}', wp_nonce_field('update-options'), $return);
    $return = str_replace('{SALD}',  ($status == 'other') ? Bolao::saldo() : '', $return);
    if (!$silent) print $return;
    return $return;
  }

  static function saldo () {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    global $current_user;
    if ($current_user->user_level == 10) return;
    $result = Bolao::$wpdb->get_results(sprintf('SELECT COUNT(*) as total FROM %sbolao_user_pool WHERE user_id = %s', Bolao::$wpdb->prefix, $current_user->ID));
    $total_participate = $result[0]->total;
    $result = Bolao::$wpdb->get_results(sprintf('SELECT COUNT(*) as total, SUM(value) AS valor FROM %sbolao_user_pool bup
                                                 JOIN %sbolao_pool bp on answer_id = right_answer_id AND bup.pool_id = bp.pool_id
                                                 WHERE user_id = %s', Bolao::$wpdb->prefix, Bolao::$wpdb->prefix, $current_user->ID));
    $total_acertos = $result[0]->total;

    list($stuff_sum, $valor_total) = Bolao::_getValues();

    $return = vsprintf(__(
              '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);">
               <p>You have participate in %1.s pools
                  and you was correctely in <strong>%2.s pools</strong>.
                  Your total score is %3.s points
                  ( %4.s points are used on stuff ).
                  Now you have <strong>%5.s points</strong>.</p>
               <p><strong>Note:</strong> With this points you can request to administrator your stuff.
                  So, if you participate more times, more changes you have to win a stuff.</strong></p>
               </div>'),array (
              $total_participate, $total_acertos, $valor_total, $stuff_sum, $valor_total-$stuff_sum
              ));
    return $return;
  }

  static function detail (array $get) {
    global $current_user;
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    $result = Bolao::$wpdb->get_results(sprintf ('SELECT * FROM %sbolao_pool WHERE pool_id = %s', Bolao::$wpdb->prefix, $id));
    $action = '';
    foreach ($get as $k=>$v)
      if (preg_match('/^details.(\d+)$/', $k, $match))
        $action = $match[1];
    if (!$action) return false;
    if ('10' === $current_user->user_level) Bolao::editPool($action);
    else Bolao::showPool($action);
  }

  static function showPool ($id) {
    global $current_user;
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    $tplObj = new FileReader(Bolao::$info['plugin_fpath'] . '/showPool.html');
    $tpl = $tplObj->read($tplObj->length());
    $result = Bolao::$wpdb->get_results(sprintf ('SELECT * FROM %sbolao_pool NATURAL JOIN %sbolao_answer WHERE pool_id = %s', Bolao::$wpdb->prefix, Bolao::$wpdb->prefix, $id));
    $has = Bolao::$wpdb->get_results (sprintf('SELECT * FROM %sbolao_user_pool WHERE user_id = %s AND pool_id = %s', Bolao::$wpdb->prefix, $current_user->id, $id));
    if (sizeof($result) === 0) { print __('<h2>Cheating who?</h2>'); return; }
    if (isset($_REQUEST) && isset($_REQUEST['vote']) && preg_match('/^\d+$/', trim($_REQUEST['item']))) {
      if (sizeof($has) == 0) {
        Bolao::$wpdb->query (sprintf('INSERT INTO %sbolao_user_pool VALUES(%s, %s, %s)', Bolao::$wpdb->prefix, $current_user->id, $id, $_REQUEST['item']));
        printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('Your vote has been computed.') );
      } else {
        printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('Your can vote in this pool one time. Your vote hasn\'t been computed.') );
      }
      $_REQUEST = array ();
      Bolao::status();
      return;
    }
    $title = $result[0]->question;
    $pool_value = $result[0]->value;
    $replacers = array (
      'DETAILS_TITLE' => __('Details of Pool'),
      'TITLE'         => $title,
      'ID'            => $result[0]->pool_id,
      'OPTIONS'       => __('Options<br/><small>Make your choice!</small>'),
      'VOTE'          => __('Vote Now!'),
    );

    foreach ($replacers as $k=>$v)
      $tpl = str_replace("{{$k}}", $v, $tpl);
    $values = array();
    foreach ($result as $n=>$item) {
      $u=uniqid();
      $readOnly = $result[0]->status == '1' || sizeof($has) ? ' disabled="disabled"' : '';
      if ($result[0]->status == 0)
        if (sizeof($has))
          $checked = $has[0]->answer_id == $item->answer_id ? ' checked="checked"' : '';
        else
          $checked = $n == 0 ? ' checked="checked"' : '';
      else
        $checked = $item->answer_id==$result[0]->right_answer_id ? ' checked="checked"' : '';
      $values[] = sprintf('<label for="vencedor_%s"><input type="radio" id="vencedor_%s" name="item" value="%s" %s %s /> %s</label>', $u, $u, $item->answer_id, $checked, $readOnly, $item->answer);
    }
    $tpl = explode ("\n", $tpl);
    foreach ($tpl as $n=>$line) {
      if (preg_match('/^(\s*)\[options\]$/', $line, $match))
        $tpl[$n] = $match[1] . implode ("<br/>\n{$match[1]}", $values);
      if ((sizeof($has) == 1 || $result[0]->status == '1') && (preg_match('/^\{AB\}/', $line, $match)))
        unset($tpl[$n]); # if the pool is closed, it have no action buttons
    }
    $tpl = implode ("\n", $tpl);
    $tpl = str_replace('{AB}', '', $tpl); # The Action Buttons
    print $tpl;
  }

  static function editPool ($id) {
    global $current_user;
    if ('10' !== $current_user->user_level) return false;
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    $tplObj = new FileReader(Bolao::$info['plugin_fpath'] . '/editPool.html');
    $tpl = $tplObj->read($tplObj->length());
    $result = Bolao::$wpdb->get_results(sprintf ('SELECT * FROM %sbolao_pool NATURAL JOIN %sbolao_answer WHERE pool_id = %s', Bolao::$wpdb->prefix, Bolao::$wpdb->prefix, $id));
    if (sizeof($result) === 0) { print __('<h2>Cheating who?</h2>'); return; }
    if (isset($_REQUEST) && isset($_REQUEST['delete'])) {
      Bolao::$wpdb->query (sprintf ('DELETE FROM %sbolao_pool   WHERE pool_id = %s', Bolao::$wpdb->prefix, $id));
      Bolao::$wpdb->query (sprintf ('DELETE FROM %sbolao_answer WHERE pool_id = %s', Bolao::$wpdb->prefix, $id));
      printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('The pool has been deleted.') );
      return;
    }
    if (isset($_REQUEST) && isset($_REQUEST['close']) && preg_match('/^\d+$/', trim($_REQUEST['item']))) {
      Bolao::$wpdb->update (Bolao::$wpdb->prefix."bolao_pool", array ('status'=>1, 'right_answer_id'=>$_REQUEST['item']), array ('pool_id' => $id));
      printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('The pool has been closed.') );
      $_POST = $_GET = $_REQUEST = array();
      Bolao::status();
      return;
    }
    $extra = '';
    if ($result[0]->status) {
      $winners = Bolao::$wpdb->get_results (vsprintf ('
                                                      SELECT * FROM %1$sbolao_user_pool
                                                      JOIN %1$susers ON %1$susers.ID = %1$sbolao_user_pool.user_id
                                                      WHERE
                                                        pool_id = %2$s AND
                                                        answer_id = %3$s
                                                      '
      , array(Bolao::$wpdb->prefix, $id, $result[0]->right_answer_id)));
      $ganhadores = array();
      foreach ($winners as $winnwer) $ganhadores[] = $winnwer->user_nicename;
      $extra = sprintf(__('<tr valign="top">
        <th>Winners</th>
        <td scope="row">%s</td>
      </tr>'), implode ("\n", $ganhadores));
    }
    $users_total = Bolao::$wpdb->get_results(sprintf ('SELECT COUNT(*)-1 as total FROM %susers', Bolao::$wpdb->prefix));
    $users_total = $users_total[0]->total;

    $users_votted = Bolao::$wpdb->get_results(sprintf ('SELECT COUNT(*) as total FROM %sbolao_user_pool WHERE pool_id = %s', Bolao::$wpdb->prefix, $id));
    $users_votted = $users_votted[0]->total;

    $title = $result[0]->question;
    $pool_value = $result[0]->value;
    $replacers = array (
      'EDIT_TITLE'   => __('Details of Pool'),
      'TITLE'        => $title,
      'ID'           => $result[0]->pool_id,
      'VALUE'        => __('Value'),
      'POOL_VALUE'   => $pool_value,
      'STATUS_TITLE' => __('Status'),
      'STATUS'       => vsprintf (__('%0.s of %1.s have been avaliate this pool. (You aren\'t counted)'), array ($users_votted, $users_total)),
      'CLOSE'        => __('Close this Pool'),
      'DELETE'       => __('Delete this Pool'),
      'OPTIONS'      => __('Options<br/><small>Select the winner!</small>'),
      'SURE_CLOSE'   => __('Are you sure do you want to CLOSE this pool?\nThis action is not reversible and users can\\\'t vote anymore on this pool.'),
      'SURE_DELETE'  => __('Are you sure do you want to DELETE this pool?\nThis action is not reversible'),
      'EXTRA'        => $extra,
    );
    foreach ($replacers as $k=>$v)
      $tpl = str_replace("{{$k}}", $v, $tpl);
    $values = array();
    foreach ($result as $n=>$item) {
      $u=uniqid();
      $readOnly = $result[0]->status == '1' ? ' disabled="disabled"' : '';
      if ($result[0]->status == 0)
        $checked = $n==0 ? ' checked="checked"' : '';
      else
        $checked = $item->answer_id==$result[0]->right_answer_id ? ' checked="checked"' : '';
      $values[] = sprintf('<label for="vencedor_%s"><input type="radio" id="vencedor_%s" name="item" value="%s" %s %s /> %s</label>', $u, $u, $item->answer_id, $checked, $readOnly, $item->answer);
    }
    $tpl = explode ("\n", $tpl);
    foreach ($tpl as $n=>$line) {
      if (preg_match('/^(\s*)\[options\]$/', $line, $match))
        $tpl[$n] = $match[1] . implode ("<br/>\n{$match[1]}", $values);
      if ($result[0]->status == '1' && preg_match('/^\{AB\}/', $line, $match))
        unset($tpl[$n]); # if the pool is closed, it have no action buttons
    }
    $tpl = implode ("\n", $tpl);
    $tpl = str_replace('{AB}', '', $tpl); # The Action Buttons
    print $tpl;
  }

  static function savePool($post) {
    if (isset($_POST) && sizeof($_POST) > 0) $post = $_POST;
    foreach ($post as $k => $v)
      $post[$k] = trim ($v);
    $itens = array ();
    foreach ($post as $k => $v)
      if (preg_match('/^pooloptions\d+$/', $k) && strlen($v) > 0)
        $itens[] = $v;
    if (!isset($post['value']))  return array (false, __('The pool must have a integer value.'));
    if (!isset($post['title']) || strlen($post['title']) === 0)  return array (false, __('The pool must have a title.'));
    if (!isset($post['submit'])) return array (false, __('Cheating who?'));
    if (sizeof($itens) < 2) return array (false, __('The pool must have two options.'));
    $post['value'] = preg_replace ('/\D/', '.', $post['value']);
    if (!preg_match ('/^\d+[\.,]?\d*$/', $post['value']))
      return array (false, __('The pool must have a value.'));

    /* Subentende-se que passou por todos os testes */
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    $_POST = array ();
    Bolao::$wpdb->insert (
      sprintf ('%sbolao_pool', Bolao::$wpdb->prefix),
      array (
        'question' => $post['title'],
        'value'  => (float) $post['value'],
        'status' => '0'
      )
    );
    $id = Bolao::$wpdb->insert_id;
    foreach ($itens as $item)
      Bolao::$wpdb->insert(
        sprintf ('%sbolao_answer', Bolao::$wpdb->prefix),
        array (
          'pool_id' => $id,
          'answer'  => $item,
        )
      );
    return array (true, $id);
  }

  static function mkMenuOptions () {
    add_menu_page ('bolao', 'Bolão', 0, __FILE__, array('Bolao', 'status'));
    add_submenu_page (__FILE__, __('Create New'), __('Create New'), 10, 'bolao_create_new', array('Bolao', 'createTpl'));
    add_submenu_page (__FILE__, __('Stuffs'), __('Stuffs'), 0, 'bolao_stuffs', array('Bolao', 'stuffs'));
    add_submenu_page (__FILE__, __('Create New'), __('Requests'), 10, 'bolao_request', array('Bolao', 'requests'));
  }

  static function createTpl($dontPrint = false) {
    global $current_user;
    $return = '';
    if (isset($_POST) && sizeof($_POST) > 0 && isset($_POST['submit']) && $_POST['submit'] == __('Save')) {
      $r = Bolao::savePool($_POST);
      if (false === $r[0]) $return = sprintf('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', $r[1]);
      else                 $return = sprintf('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('The Pool has been saved.'));
    }
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    if ($current_user->user_level != 10) return;
    $tplObj = new FileReader(Bolao::$info['plugin_fpath'] . '/create.html');
    $tpl = $tplObj->read($tplObj->_length);
    $campos = array (
      'RETURN'              => $return,
      'ACTION'              => 'admin.php?page=' . $_GET['page'],
      'TITLE'               => __('Bolão'),
      'TITLE_OF_POOL'       => __('Title of pool'),
      'VALUE_TITLE_OF_POOL' => $_POST['title'],
      'VALUE'               => __('Value'),
      'VALUE_OF_POOL'       => $_POST['value'],
      'THE_OPTIONS'         => __('Options'),
      'OPTIONS'             => Bolao::optionsInputs (),
      'SAVE'                => __('Save'),
    );
    foreach ($campos as $key => $value)
    	$tpl = str_replace ("{{$key}}", $value, $tpl);
    if ($dontPrint == false) print $tpl;
    return $tpl;
  }

  static function optionsInputs ($post=array(), $action='') {
    if (sizeof($post) == 0) $post = array();
    if (isset($_POST) && sizeof($_POST) > 0)
      foreach ($_POST as $k => $v)
        if (strpos($k, 'pooloptions') === 0)
          $post[] = $v;
    if (!$action) $action = Bolao::_getAction($_POST);
    for ($i=count($post); $i < 2;$i++)
      $post[] = '';
    $options = array();
    $i = $j = 0;
    $bnt = '';
    foreach ($post as $item) {
      $j++;
      if (preg_match('/^remove\d*$/', $action) && preg_replace('/\D/', '', $action) == $j) continue;
      $i++;
      if ($i > 2)
        $bnt = sprintf (' <input type="submit" name="remove%s" value="%s"  />', $i, __('Remove'));
      $options[] = sprintf ('<input type="text" name="pooloptions%s" value="%s" />%s', $i, $item, $bnt);
    }
    $bnt = sprintf (' <input type="submit" name="remove%s" value="%s"  />', ++$i, __('Remove'));
    if ($action == 'add' || isset($_POST['add']))
      $options[] = sprintf ('<input type="text" name="pooloptions%s" value="" />%s', $i, $bnt);
    $add_button = sprintf ('<br/><input type="submit" name="add" value="%s"  />', __('Add'));
    return sprintf ("\n  %s\n  %s\n", implode ("<br/>\n  ", $options), $add_button);
  }

  static function stuffForm () {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    global $current_user;
    if ($current_user->user_level != 10 || !isset($_GET['new'])) {
      printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('Cheating who?.') );
      return;
    }
    $tplObj = new FileReader(Bolao::$info['plugin_fpath'] . '/stuffForm.html');
    $tpl = $tplObj->read($tplObj->_length);
    $phrases = array(
      'TITLE'   => __('New Stuff'),
      'NAME'    => __('Name'),
      'VALUE'   => __('Value'),
      'DESC'    => __('Description'),
      'SAVE'    => __('Save'),
      'V_NAME'  => $_GET['name'],
      'V_VALUE' => $_GET['value'],
      'V_DESC'  => $_GET['desc'],
    );
    foreach ($phrases as $key=>$value)
      $tpl = str_replace("{{$key}}", $value, $tpl);
    print $tpl;
  }

  static function stuffs () {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    global $current_user;

    if ($current_user->user_level == 10 && isset($_GET['new']))
      return Bolao::stuffForm();

    if ($current_user->user_level == 10 && isset($_GET['newhandle'])) {
      $errs = array();
      foreach (array('name' => __('Name must have a value'), 'value' => __('Value must have a value'), 'desc' => __('Description must have a value')) as $k=>$v)
        if (!isset($_GET[$k]) || '' === trim($_GET[$k]))
          $errs[$k] = $v;
      if (isset($_GET['value']) && !preg_match('/^\d+[\.,]?\d*$/', trim($_GET['value'])))
        $errs['value'] = __('Value must have a money format (Eg: 15.83)');
      else
        $_GET['value'] = preg_replace ('/\D/', '.', $_GET['value']);
      if (sizeof($errs) > 0) {
        printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p><ul><li>%s</li></ul></div>', __('Have errors on your data, please get back and verify this questions:'), implode ('</li><li>', $errs) );
        $_GET['new'] = 1;
        return Bolao::stuffForm();
      } else {
        Bolao::$wpdb->insert (Bolao::$wpdb->prefix.'bolao_stuff', array(
          'name'  => $_GET['name'],
          'desc'  => $_GET['desc'],
          'value' => $_GET['value'],
        ));
        printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('The Stuff have been saved.') );
      }
    }

    if (isset($_GET) && $current_user->user_level != 10) {
      list($stuff_sum, $valor_total) = Bolao::_getValues();
      $saldo = $valor_total-$stuff_sum;

      foreach ($_GET as $k=>$v) {
      	if (preg_match('/^require.(\d+)/', $k, $matches)) {
      	  $result = Bolao::$wpdb->get_results (sprintf('SELECT value FROM %sbolao_stuff WHERE stuff_id = %s', Bolao::$wpdb->prefix, $matches[1]));
      	  if ($result[0]->value > $saldo) {
      	    printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('You can\'t request this stuff. Check your limit of points.') );
      	  } else {
        	  Bolao::$wpdb->insert (Bolao::$wpdb->prefix . 'bolao_user_order', array(
        	    'user_id'  => $current_user->ID,
        	    'stuff_id' => $matches[1]
        	  ));
        	  printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('Your request have been send.') );
      	  }
      	}
      }
    }
    list($stuff_sum, $valor_total) = Bolao::_getValues();
    $saldo = $valor_total-$stuff_sum;

    $tplObj = new FileReader(Bolao::$info['plugin_fpath'] . '/stuffs.html');
    $tpl = $tplObj->read($tplObj->_length);
    $status = ($current_user->user_level == 10) ? '' : sprintf(__('<p>Now you have <strong>%s points</strong>.</p>'), $saldo);
    $phrases = array(
      'STUFFS'  => ($current_user->user_level == 10) ? __('Stuffs (<a href="?page=bolao_stuffs&new=new">Create New</a>)') : __('Stuffs'),
      'NAME'    => __('Name'),
      'DESC'    => __('Description'),
      'VALUE'   => __('Value'),
      'ACTIONS' => ($current_user->user_level == 10) ? '' : __('Actions'),
      'STATUS'  => $status,
    );
    foreach ($phrases as $key=>$value)
      $tpl = str_replace("{{$key}}", $value, $tpl);
    $lines = split("\n", $tpl);
    $result = Bolao::$wpdb->get_results (sprintf('SELECT * FROM %sbolao_stuff', Bolao::$wpdb->prefix));
    $tds = array();
    foreach ($result as $item)
      if ($current_user->user_level == 10)
        $tds[] = vsprintf(__('<tr valign="top"><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>&nbsp;</td></tr>'),
                          array ($item->stuff_id, $item->name, $item->desc, $item->value));
      else
        $tds[] = vsprintf(__('<tr valign="top"><td>%2$s</td><td>%3$s</td><td>%4$s</td><td><input type="submit" class="button" name="require %1$s" value="Require"  /></td></tr>'),
                          array ($item->stuff_id, $item->name, $item->desc, $item->value));
    foreach ($lines as $n => $line)
      if (preg_match('/^(\s*)\[loop\]/', $line, $match))
        $lines[$n] = $match[1] . implode($match[1], $tds);
    print implode("\n", $lines);
  }

  static function requests () {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    global $current_user;
    if ($current_user->user_level != 10) return;

    if (isset($_GET['check']) && gettype($_GET['check']) == 'array' && sizeof($_GET['check']) > 0) {
      foreach ($_GET['check'] as $item) {
        Bolao::$wpdb->update (Bolao::$wpdb->prefix.'bolao_user_order', array('status' => 1), array('user_order_id' => $item));
      }
      printf ('<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>%s</strong></p></div>', __('The checked boxes have been confirmed! Tank You.') );
    }


    $result = Bolao::$wpdb->get_results(vsprintf('SELECT * FROM %1$sbolao_user_order buo
                                                 LEFT JOIN %1$susers u ON u.ID = buo.user_id
                                                 NATURAL JOIN %1$sbolao_stuff
                                                 WHERE status = 0', array(Bolao::$wpdb->prefix)));
    $itens = array();
    foreach ($result as $item) {
      $itens[] = sprintf('
                          <tr>
                            <td><input type="checkbox" name="check[]" value="%s"  /></td>
                            <td>%s</td>
                            <td>%s</td>
                            <td>%s</td>
                            <td>%s</td>
                          </tr>
                          ', $item->user_order_id, $item->user_nicename, $item->user_email, $item->name, $item->value);
    }
    $tplObj = new FileReader(Bolao::$info['plugin_fpath'] . '/requests.html');
    $tpl = $tplObj->read($tplObj->_length);
    $phrases = array(
      'TITLE' => __('Requests Pending'),
      'CHECK' => __(''),
      'USER'  => __('User'),
      'EMAIL' => __('E-mail'),
      'ITEM'  => __('Item'),
      'VALUE' => __('Value'),
      'CONFIRM' => __('Confirm the send of selected stuffs'),
    );
    foreach ($phrases as $key=>$value)
      $tpl = str_replace("{{$key}}", $value, $tpl);

    $tpl = explode ("\n", $tpl);
    foreach ($tpl as $n=>$line) {
      if (preg_match('/^(\s*)\[loop\]$/', $line, $match))
        $tpl[$n] = $match[1] . implode ("\n{$match[1]}", $itens);
    }
    $tpl = implode ("\n", $tpl);

    print $tpl;
  }

  static function widget (array $options = array ()) {
    if ( is_null(Bolao::$wpdb) ) Bolao::init();
    $wpdb = Bolao::$wpdb;
    /* @var $wpdb wpdb */
    global $current_user;
    $default_options = array (
      'title'          => 'Bolao',
      'title_start'    => '<h2 class="bolao_title">',
      'title_end'      => '</h2>',
      'question_start' => '<li class="bolao_question"><h3>',
      'question_end'   => '</h3></li>',
      'options_start'  => '<ul>',
      'opt_start'      => '<li class="bolao_option {EVEN-ODD}">',
      'opt_end'        => '</li>',
      'options_end'    => '</ul>',
      'submit_start'   => '<li class="bolao_submit">',
      'submit_end'     => '</li>',
      'print'          => true,
    );

    $options = array_merge( $default_options, $options);

    $title = sprintf ('%s%s%s', $options['title_start'], $options['title'], $options['title_end']);

    $pool = $wpdb->get_results(sprintf('SELECT * FROM %sbolao_pool ORDER BY pool_id DESC LIMIT 1', $wpdb->prefix));

    if (sizeof($pool) === 0) return;

    $question = sprintf("%s%s%s", $options['question_start'], $pool[0]->question, $options['question_end']);

    $answers = $wpdb->get_results(sprintf('SELECT * FROM %sbolao_answer WHERE pool_id = %s', $wpdb->prefix, $pool[0]->pool_id));

    $list_answers = array();
    $i = 0;
    foreach ($answers as $answer) {
      $i++;
      $uid = uniqid();
      $even = $i % 2 ? 'even' : 'odd';
      $input = sprintf ('
        <label for="bolao_option_%s">
          <input type="radio" name="item" value="%s" id="bolao_option_%s"  />
          %s
        </label>
      ', $uid, $answer->answer_id, $uid, $answer->answer);
    	$list_answers[] = sprintf('      %s%s%s', str_replace('{EVEN-ODD}', $even, $options['opt_start']), $input, $options['opt_end']);
    }
    $input = vsprintf ('<input type="submit" name="vote" value="%1$s"  />', array(__('Vote')));
  	$list_answers[] = sprintf("      %s%s%s", $options['submit_start'], $input, $options['submit_end']);
    $list_answers = sprintf ("  %s\n      %s\n%s\n  %s", $options['options_start'], $question, implode("\n", $list_answers), $options['options_end']);

    $address = get_option('siteurl') . '/wp-admin/admin.php';

    $details = sprintf('<input type="hidden" name="details %s" value="details %s" />', $pool[0]->pool_id, $pool[0]->pool_id);

    $form = sprintf('
<form action="%s" method="get" class="bolao_form">
  %s
  %s
  %s
%s
</form>',
      $address,
      '<input type="hidden" name="page" value="bolao/bolao.php" />',
      '<input type="hidden" name="handle" value="handle" />',
      $details,
      $list_answers
    );
    $return = sprintf("%s\n\n%s", $title, $form);
    if ($options['print'] === true) print $return;
    return $return;
  }
}

function bolao_widget (array $options = array()){ return Bolao::widget($options); }

$ucmPluginFile = substr(strrchr(dirname(__FILE__),DIRECTORY_SEPARATOR),1).DIRECTORY_SEPARATOR.basename(__FILE__);
register_activation_hook($ucmPluginFile, array('Bolao','install'));
register_deactivation_hook($ucmPluginFile, array('Bolao','uninstall'));

add_filter('init', array('Bolao','init'));

?>