@extends(config('redprint.redprint-layout', 'redprintUnity::page'))

@section('title') Dashboard @stop

@section('page_title') Redprint Relationship Builder @stop
@section('page_subtitle') Connect Models @stop
@section('page_icon') <i class="icon-layers"></i> @stop

@section('css')
  @parent
  <link rel="stylesheet" type="text/css" href="/vendor/redprint/css/redprint.css">
@stop

@section('content')
    <div class="card" id="app">
        <div class="card-body">
          <a href="#" data-toggle="modal" data-target="#newRelation" class="btn btn-success pull-right"><i class="fa fa-code-fork"></i>New Relation</a>

          <div class="row">
            @foreach ($relations as $relation)
            <div class="col-md-6">
              <div class="tree">
                <ul>
                  <li>
                    <a href="#">{{ $relation['model'] }}</a>
                    <ul>
                      @foreach($relation['data'] as $data)
                      <li>
                        <a href="#">{{ $data['type'] }}</a>
                        <ul>
                          <li>
                            <a href="#">{{ $data['model'] }}</a>
                            <ul>
                              <li><a href="#">{{ $data['foreign_key'] }}</a></li>
                              <li><a href="#">{{ $data['local_key'] }}</a></li>
                            </ul>
                          </li>
                        </ul>
                      </li>
                      @endforeach
                    </ul>
                  </li>
                </ul>
              </div>
            </div>
             @endforeach
          </div>
        
    </div>
</div>

@section('modals')
@parent
<div class="modal fade" tabindex="-1" role="dialog" id="newRelation">
  <form method="post" action="{{ route('redprint.relationship.new') }}">
  {!! csrf_field() !!}
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Relation</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="model">Base Model</label>
          <select name="model" class="form-control">
            @foreach ($models as $model)
              <option value="{{ $model }}">{{ $model }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label for="relationship">Relationship</label>
          <select name="relationship" class="form-control">
            @foreach ($relationshipTypes as $type)
              <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label for="with">With</label>
          <select name="with" class="form-control">
            @foreach ($models as $model)
              <option value="{{ $model }}">{{ $model }}</option>
            @endforeach
          </select>
        </div>

      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Build Relationship</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
  </form>
</div>
@stop

@stop