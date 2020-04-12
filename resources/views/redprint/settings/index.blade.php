@extends(config('redprint.redprint-layout', 'redprintUnity::page'))

@section('title') Settings @stop

@section('page_title') Settings @stop
@section('page_subtitle') Settings @stop

@section('content')
  <div id="app">

    <el-tabs type="border-card">
      
      <!-- ENV -->
      <el-tab-pane label=".env" v-loading="loading">
        <div class="row">
          <div class="form-group col-md-6" v-for="item in env">
            <label>@{{ item.key }}</label>
            <input type="text" v-model="item.value" class="form-control" :disabled="item.protected">
          </div>
        </div>

        <el-button type="success" plain @click.prevent="saveEnv" :loading="saving">Save Changes</el-button>
      </el-tab-pane>

      <!-- Permissible -->
      <el-tab-pane label="config/permissible.php" v-loading="loading">

        <div class="row">
          <div class="form-group col-md-6" v-for="item in permissible">
            <label>@{{ item.key }}</label>
            <input type="text" v-model="item.value" class="form-control" :disabled="item.protected">
          </div>
        </div>

        <el-button type="success" plain @click.prevent="savePermissibleConfig" :loading="saving">Save Changes</el-button>
      </el-tab-pane>

      <!-- Redprint -->
      <el-tab-pane label="config/redprint.php" v-loading="loading">

        <div class="row">
          <div class="form-group col-md-6" v-for="item in redprint">
            <label>@{{ item.key }}</label>
            <input type="text" v-model="item.value" class="form-control" :disabled="item.protected">
          </div>
        </div>

        <el-button type="success" plain @click.prevent="saveRedprintConfig" :loading="saving">Save Changes</el-button>
      </el-tab-pane>

      <!-- Theme -->
      <el-tab-pane label="config/redprint-unity.php" v-loading="loading">

        <div class="row">
          <div class="form-group col-md-6" v-for="item in theme">
            <label>@{{ item.key }}</label>
            <input type="text" v-model="item.value" class="form-control" :disabled="item.protected">
          </div>
        </div>

        <el-button type="success" plain @click.prevent="saveThemeConfig" :loading="saving">Save Changes</el-button>
      </el-tab-pane>

    </el-tabs>

  </div>
@stop


@section('head-js')
    @parent
    <script>
      window.redprint_demo = {{ json_encode(redprint_demo() )}}
    </script>
    
    <script src="/vendor/redprint/vendor/vue/2.6.4/vue.min.js"></script>
    <script src="/vendor/redprint/vendor/axios/0.18.0/axios.min.js"></script>
    <script src="/vendor/redprint/vendor/element-ui/2.5.4/index.js"></script>
    <script src="/vendor/redprint/vendor/lodash/lodash.min.js"></script>
@stop


@section('post-js')
@parent
  <script src="/vendor/redprint/js/settings.js" defer></script>
@stop


@section('css')
@parent
  <style type="text/css">
    
    .form-group label {
      margin-top: 5px;
      background: rgb(245, 247, 250);
      color: #5585d8;
      padding: 2px 0px 2px 8px;
      clear: both;
      position: relative;
      display: block;
      width: 100%;
      border-top-left-radius: 5px;
      border-top-right-radius: 5px;
      margin-bottom: 0px;
    }

  </style>
  <link rel="stylesheet" type="text/css" href="/vendor/redprint/vendor/element-ui/2.5.4/index.css">
  <link rel="stylesheet" type="text/css" href="/vendor/redprint/css/settings.css">
@stop