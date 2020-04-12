@extends(config('redprint.redprint-layout', 'redprintUnity::page'))

@section('title') Dashboard @stop

@section('page_title') Dashboard @stop
@section('page_subtitle') Overview @stop

@section('content')
  <div class="row" id="app" v-loading="loading">
    <p v-if="message.length">@{{ message }}</p>
    <div class="col-md-4" v-for="crud in cruds">
      
      <div class="card">
        <div class="card-header">
          @{{ crud.model }}
        </div>
        <div class="card-body">
          <p>Files Generated:</p>
          <ul class="list-group">
            <li
              :class="fileListItemClass(file.path)"
              v-for="file in crud.files"
            >
              <a href="#" @click.prevent="loadFile(file.path)">@{{ file.path }}</a>
            </li>
          </ul>
        </div>
        <div class="card-footer">
          <el-button plain type="danger" @click.prevent="undo(crud)">Remove CRUD @{{crud.model}} </el-button>
          <el-button plain type="primary" @click.prevent="modify(crud)">Modify Migrations @{{crud.model}} </el-button>
        </div>
      </div>

    </div>


    <!-- Quick Editor -->
    <el-dialog
      :title="'Editing: ' + filePath"
      :visible.sync="editorVisible"
      width="80%"
      :before-close="handleClose">

        <div class="row">
          <div class="col-md-12">
            <ul class="nav nav-pills">

              <li class="nav-item">
                <a class="nav-link disabled" href="#">Change Theme:</a>
              </li>

              <!-- Light -->
              <li class="nav-item">
                <a :class="themeNavLink('kuroir')" href="#" @click.prevent="changeTheme('kuroir')">Kuroir</a>
              </li>
              <li class="nav-item">
                <a :class="themeNavLink('xcode')" href="#" @click.prevent="changeTheme('xcode')">Xcode</a>
              </li>

              <!-- Dark -->
              <li class="nav-item">
                <a :class="themeNavLink('monokai')" href="#" @click.prevent="changeTheme('monokai')">Monokai</a>
              </li>

              <li class="nav-item">
                <a :class="themeNavLink('twilight')" href="#" @click.prevent="changeTheme('twilight')">Twilight</a>
              </li>

              <li class="nav-item">
                <a :class="themeNavLink('tomorrow_night')" href="#" @click.prevent="changeTheme('tomorrow_night')">Tomorrow Night</a>
              </li>

            </ul>
          </div>

        </div>

        <div class="row">
          <div class="col-md-12">
            <div id="editor"></div>
          </div>
        </div>

        <span slot="footer" class="dialog-footer">
          <el-button
            type="success" 
            plain 
            @click.prevent='saveChanges' 
            :loading="saving"
            v-if="filePath !== ''"
          >Save Changes</el-button>
          <el-button type="warning" plain @click="editorVisible = false">Close</el-button>
        </span>
    </el-dialog>


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
@stop


@section('post-js')
@parent
    <script src="/vendor/redprint/vendor/ace/src-min/ace.js" type="text/javascript" charset="utf-8"></script>
    <script src="/vendor/redprint/js/generated.js" defer></script>
@stop


@section('css')
@parent
<link rel="stylesheet" href="/vendor/redprint/vendor/element-ui/2.5.4/index.css">
<style type="text/css">
  #editor { 
      position: relative;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;
      min-height: 700px;
      min-width: 100%;
  }
  .list-group-item {
    padding: 0.3rem 1.25rem;
  }
  .list-group-item.active a {
    color: #fff;
  }
  .list-group-item a {
    display: block;
    width: 100%;
    font-size: 11px;
  }
</style>
@stop