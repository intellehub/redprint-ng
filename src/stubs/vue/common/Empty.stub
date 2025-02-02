<template>
    <div class="w-full text-center no-data-block">
        <emptysvg style="text-align: center; margin: 0 auto;"></emptysvg>
        <h2 v-if="title">{{ title }}</h2>
        <br/>
        <span v-if="detail">{{ detail }}</span
        ><br/><br/>
        <el-button plain type="info" size="small"
                   v-if="button_link && button_text"
                   href="#"
                   @click.prevent="$goTo(button_link)"
                   class="add-tag-button"
        >{{ button_link }}
        </el-button
        >
        <el-button plain type="info" size="small"
                   v-if="action && call_to_action"
                   href="#"
                   @click.prevent="call_to_action()"
                   class="add-tag-button"
        >{{ action }}
        </el-button
        >
    </div>
</template>

<script>
import emptysvg from '@/assets/images/illustration/images.svg?component'

export default {
    name: `Empty`,
    props: [
        "title",
        "detail",
        "button_text",
        "button_link",
        "call_to_action",
        "action",
        "loading"
    ],
    data() {
        return {};
    },
    components: {
        emptysvg
    }
};
</script>

<style lang="scss">
.no-data-block {
    width: 100%;
    margin: 0 auto;
    text-align: center;
    padding: 3%;

    svg {
        height: 150px;
        width: auto;
    }
}
</style>
