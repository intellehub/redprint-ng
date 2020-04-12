@extends(config('redprint.redprint-layout', 'redprintUnity::page'))

@section('title') Dashboard @stop

@section('page_title') Dashboard @stop
@section('page_subtitle') Overview @stop

@section('content')
<div id="app">

  <div id="editorPanel" class="row">

    <transition name="fade">
      <div class="feedback-overlay" v-if="showFeedbackOverlay">
        <p v-text="feedbackText"></p>
      </div>
    </transition>

    <div class="col-xl-3 col-lg-3 col-md-3 col-xs-12" v-show="showFileBrowserMenu" v-loading="loadingSidebarFileList">

      <div :class="sideBarClass">
        <filelist
          v-for="section in fileSections"
          :collapsed="section.collapsed"
          :title="section.name.toUpperCase()"
          :identifier="section.name"
          :defaultdir="section.defaultDir"
          :files="sidebarFileList[section.name]"
          :createnewfile="createNewFile"
          :loadfile="loadFile"
          :itemclass="fileListItemClass"
          :hidepopover="hidePopover"
          :deletefile="deleteFile"
          :newfile="newFile"
        ></filelist>
      </div>
    </div>

    <div :class="editorSpan">
      <div class="row">
        <div class="col-md-9">
          <ul class="nav nav-pills">

            <li class="nav-item">
              <a class="nav-link disabled" href="#">Files:</a>
            </li>

            <!-- Light -->
            <li class="nav-item" v-for="(file, index) in openFiles" :key="index">
              <el-tooltip :content="file.path" placement="top">
                <a 
                  :class="fileIsActive(file.path)" 
                  href="#" @click.prevent="switchToFile(index)">
                    @{{ file.name.length > 8 ? file.name.substring(0, 6) + '...' : file.name + '.' + file.ext }}
                    &nbsp;&nbsp;<i class="el-icon-error" @click.prevent="closeFile(index)"></i>
                </a>
              </el-tooltip>
            </li>

          </ul>

        </div>
        <div class="col-md-3">
          <a href="#" @click.prevent="distractionFreeToggle" :class="toolbarIcon(distractionFreeEditor)"><i class="icon-enlarge"></i></a>
          <a href="#" @click.prevent="fileBrowserMenuToggle" :class="toolbarIcon(showFileBrowserMenu)"><i class="icon-folder-open"></i></a>

          <el-popover
            width="250"
            v-model="showThemer">

              <p>Choose theme:</p>
              <ul class="list-group">

                <!-- Light -->
                <li :class="themeNavLink('kuroir')">
                  <a href="#" @click.prevent="changeTheme('kuroir')">Kuroir</a>
                </li>
                <li :class="themeNavLink('xcode')">
                  <a href="#" @click.prevent="changeTheme('xcode')">Xcode</a>
                </li>

                <!-- Dark -->
                <li :class="themeNavLink('monokai')">
                  <a href="#" @click.prevent="changeTheme('monokai')">Monokai</a>
                </li>

                <li :class="themeNavLink('twilight')">
                  <a href="#" @click.prevent="changeTheme('twilight')">Twilight</a>
                </li>

                <li :class="themeNavLink('tomorrow_night')">
                  <a href="#" @click.prevent="changeTheme('tomorrow_night')">Tomorrow Night</a>
                </li>

              </ul>

            <a href="#" slot="reference" class="filebrowser-toolbar-item"><i class="icon-paint-format"></i></a>
          </el-popover>

          <a href="#" @click.prevent="toggleSpotlight" :class="toolbarIcon(spotlight)"><i class="icon-search"></i></a>


          <el-popover
            width="250"
            v-model="showHelp">

              <p>Shortcuts:</p>
              <ul class="list-group">

                <li class="list-group-item">
                  <a href="#" @click.prevent="toggleSpotlight()">ctrl + alt + p (Search Files)</a>
                </li>

                <li class="list-group-item">
                  <a href="#" @click.prevent="saveChanges()">ctrl + s (Save)</a>
                </li>

                <li class="list-group-item">
                  <a href="#" @click.prevent="toggleSpotlight(true)">Esc (Exit file search)</a>
                </li>

                <li class="list-group-item">
                  <a href="#" @click.prevent="distractionFreeToggle()">ctrl + alt + f (Toggle Full screen editor)</a>
                </li>
                <li class="list-group-item">
                  <a href="#" @click.prevent="fileBrowserMenuToggle()">ctrl + alt + b (Toggle File browser)</a>
                </li>
                
              </ul>

            <a href="#" slot="reference" class="filebrowser-toolbar-item"><i class="icon-help"></i></a>
          </el-popover>


        </div>
      </div>

      <div class="row">
        <div class="col-md-12">
          <div id="editor">@{{ defaultEditorContent }}</div>
        </div>
      </div>

      <div class="row" v-if="filePath !== ''">
        <div class="col-md-12">
            <br />
            <el-button type="primary" @click.prevent='saveChanges' :loading="saving" >Save Changes</el-button>
        </div>
      </div>
    </div>

    <div class="spotlight" v-show="spotlight">
      <input 
        type="text" 
        class="form-control" 
        ref="spotlightInput" 
        placeholder="Start typing file name..."
        @keyup.prevent="searchFileList"
        v-model="spotlightInput"
      >
      <div class="panel" v-loading="loadingFileList">
        <small v-if="fileSearchQueryIsDirty">Typing...<br /></small>
        <small v-if="isFetchingFileList">Fetching file list...</small>
        <ul class="list-group">
          <li 
            class="list-group-item" 
            v-for="file in spotlightFileList" 
            :key="file.path"
          >
              <el-tooltip :content="file.path" placement="top">
                <a href="#" @click.prevent="loadFile(file, true)">@{{ file.name + '.' + file.ext }}<span class="searchFilePath">@{{ file.path }}</span></a>
              </el-tooltip>
          </li>
        </ul>
      </div>
    </div>

  </div>

</div>


  <!-- Files List Accordion for Sidebar -->
  <template id="filelist">

    <div :id="identifier + 'Accordion'">
      <div class="card">
        <div class="card-header" :id="identifier + 'Heading'">
          <h5 class="mb-0">
            <a class="btn btn-link" href="#" @click.prevent="">
                <span 
                  data-toggle="collapse" 
                  :data-target="'#collapse' + identifier" 
                  aria-expanded="true" 
                  :aria-controls="'collapse' + identifier" 
                  class="float-left"
                >@{{ title }}</span>
                <el-popover
                  placement="top"
                  title="Create New File"
                  width="300"
                  ref="newFileOne"
                >
                  <form @submit.prevent="createnewfile(defaultdir)">
                    <div class="form-group">
                      <input type="text" ref="newFileInputOne" id="newFileInputOne" v-model="newfile.name" class="form-control" placeholder="e.g., files/more/file.php">
                    </div>
                  
                    <div style="text-align: right; margin: 0">
                      <el-button size="mini" type="text" @click.prevent="hidepopover">cancel</el-button>
                      <el-button type="primary" size="mini" @click.prevent="createnewfile(defaultdir)">confirm</el-button>
                    </div>
                  </form>

                  <i slot="reference" class="el-icon-circle-plus float-right" @click.prevent="focusInputOne"></i>
                </el-popover>

            </a>
          </h5>
        </div>

        <div :id="'collapse' + identifier" :class="collapsed ? 'collapse' : 'collapse show'" :aria-labelledby="identifier + 'Heading'" :data-parent="'#' + identifier + 'Accordion'">
          <div class="card-body">
              <div v-for="(collection, dir) in files" >
                <span class="dir">
                  @{{ dir ? dir : defaultdir }}
                  <el-popover
                    placement="top"
                    title="Create New File"
                    width="300"
                    ref="newFileTwo"
                  >
                    <form @submit.prevent="createnewfile(dir ? defaultdir + '/' + dir : defaultdir)">
                      <div class="form-group">
                        <input type="text" ref="newFileInputTwo" id="newFileInputTwo" v-model="newfile.name" class="form-control" placeholder="e.g., files/more/file.php">
                      </div>
                    
                      <div style="text-align: right; margin: 0">
                        <el-button size="mini" type="text" @click.prevent="hidepopover">cancel</el-button>
                        <el-button type="primary" size="mini" @click.prevent="createnewfile(dir ? defaultdir + '/' + dir : defaultdir)">confirm</el-button>
                      </div>
                    </form>

                    <i slot="reference" class="el-icon-circle-plus float-right" @click.prevent="focusInputTwo"></i>
                  </el-popover>
                </span>
                <ul class="list-group">
                  <li v-for="file in collection" :class="itemclass(file.path)" :key="file.path">
                      <span class="fileNameSpan" @click.prevent="loadfile(file)" v-text="file.name + '.' + file.ext"></span>
                      <el-popover
                        placement="top"
                        title="Delete file?"
                        width="300"
                        :popper-options="{ boundariesElement: '#app' }"
                      >
                        <p>Are you sure to delete this file <code v-text="file.name + '.' + file.ext"></code> ? It may make your system unstable and the action is not reversible.</p>
                        <div style="text-align: right; margin: 0">
                          <el-button size="mini" type="text" @click.prevent="hidepopover">cancel</el-button>
                          <el-button type="primary" size="mini" @click.prevent="deletefile(file.path)">confirm</el-button>
                        </div>


                        <i slot="reference" class="el-icon-error float-right"></i>
                      </el-popover>
                    
                  </li>
                </ul>
              </div>
          </div>
        </div>

      </div>
    </div>


  </template>


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
    <script src="/vendor/redprint/vendor/screenfull/screenfull.min.js"></script>
@stop


@section('post-js')
@parent
  <script src="/vendor/redprint/vendor/ace/src-min/ace.js" charset="utf-8"></script>
  <script src="/vendor/redprint/js/dashboard.js" defer></script>
@stop


@section('css')
@parent
  <link rel="stylesheet" type="text/css" href="/vendor/redprint/vendor/element-ui/2.5.4/index.css">
  <link rel="stylesheet" type="text/css" href="/vendor/redprint/css/dashboard.css">
@stop